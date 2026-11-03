<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http\Panel;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Cart\Money;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\BeginRefused;
use WebxUi\Payments\GatewayAmount;
use WebxUi\Payments\GatewayRefused;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\HoldRefused;
use WebxUi\Payments\MarkRefused;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Panel\OrderPaymentCard;
use WebxUi\Payments\Payments;
use WebxUi\Payments\ProblemRefused;
use WebxUi\Payments\SettleResult;

/**
 * The tab «Payment» of the order's card (spec §13.4), patched onto `orders.show`: the method of the
 * order with its bank details, the state, the attempts with their events of the exchange by time,
 * and the actions — which are the order's, so `orders.manage` (§13.1): «Mark as paid», «Check with
 * the gateway», «Change the method», «Payment link», «Problem resolved», and «Capture» / «Release the hold» of money held.
 *
 * The tab reads for `payments.view`; the bodies of the events open in the journal's own event, for
 * `payments.exchange`.
 */
final class OrderPaymentController
{
    public function __construct(
        private readonly OrderPaymentCard $tab,
        private readonly Methods $methods,
        private readonly Money $money,
    ) {}

    public function show(Request $request, int $order): JsonResponse
    {
        return ApiResponse::data($this->card($request, $this->order($order)));
    }

    /** «Mark as paid» (§12.2): the document's number, the date, a note — never an amount (decision 23). */
    public function markPaid(Request $request, int $order, Payments $payments): JsonResponse
    {
        $model = $this->order($order);
        /** @var array{reference?: string|null, paid_at?: string|null, note?: string|null} $valid */
        $valid = $request->validate([
            'reference' => ['nullable', 'string', 'max:190'],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $payments->markPaid(
                $model,
                'panel',
                $valid['reference'] ?? null,
                isset($valid['paid_at']) ? CarbonImmutable::parse($valid['paid_at']) : null,
                $valid['note'] ?? null,
            );
        } catch (MarkRefused $refused) {
            throw ValidationException::withMessages(['order' => [$refused->getMessage()]]);
        }

        return ApiResponse::data($this->card($request, $model->refresh()));
    }

    /** «Check with the gateway»: the latest attempt that is not finished (§13.4). */
    public function check(Request $request, int $order, Payments $payments): JsonResponse
    {
        $model = $this->order($order);
        $payment = $this->tab->unfinished($model);

        if ($payment === null) {
            throw ValidationException::withMessages(['order' => [__('webx-payments::panel.errors.nothing_to_check')]]);
        }

        try {
            $payments->check($payment, 'panel', wait: 5);
        } catch (GatewayUnavailable) {
            throw ValidationException::withMessages(['order' => [__('webx-payments::panel.errors.gateway_unavailable')]]);
        }

        return ApiResponse::data($this->card($request, $model->refresh()));
    }

    /** «Problem resolved» with a note (§9.4). */
    public function resolve(Request $request, int $order, int $payment, Payments $payments): JsonResponse
    {
        $model = $this->order($order);
        /** @var Payment $row */
        $row = Payment::query()->where('order_id', $model->id)->findOrFail($payment);

        try {
            $payments->resolveProblem($row, 'panel', is_string($request->input('note')) ? $request->input('note') : null);
        } catch (ProblemRefused $refused) {
            throw ValidationException::withMessages(['payment' => [$refused->getMessage()]]);
        }

        return ApiResponse::data($this->card($request, $model->refresh()));
    }

    /**
     * «Capture» of a hold (§9.5): all of it, or the part typed — where the gateway takes a part
     * (decision 52). `dry_run` answers what would be captured and nothing is asked of the gateway.
     * A refusal of the gateway leaves the money held with the problem «capture refused».
     */
    public function capture(Request $request, int $order, int $payment, Payments $payments): JsonResponse
    {
        $model = $this->order($order);
        $row = $this->attempt($model, $payment);
        $valid = $request->validate([
            'amount' => ['nullable', 'string', 'max:32'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);
        $amount = $this->amount($valid['amount'] ?? null, $row->currency);
        $dryRun = (bool) ($valid['dry_run'] ?? false);

        $result = $this->hold(fn (): SettleResult => $payments->capture($row, $amount, 'panel', $dryRun));

        if ($dryRun) {
            return ApiResponse::data($this->preview($row, $amount ?? (int) ($row->authorized_amount ?? $row->amount), $model));
        }

        if ($result->problem === SettleResult::CAPTURE_FAILED) {
            throw ValidationException::withMessages(['payment' => [__('webx-payments::panel.errors.capture_refused')]]);
        }

        return ApiResponse::data($this->card($request, $model->refresh()));
    }

    /** «Release the hold» (§9.5): the money goes back to the buyer, the attempt is cancelled. */
    public function void(Request $request, int $order, int $payment, Payments $payments): JsonResponse
    {
        $model = $this->order($order);
        $row = $this->attempt($model, $payment);
        $dryRun = $request->boolean('dry_run');

        $this->hold(fn (): SettleResult => $payments->void($row, 'panel', $dryRun));

        if ($dryRun) {
            return ApiResponse::data($this->preview($row, (int) ($row->authorized_amount ?? $row->amount)));
        }

        return ApiResponse::data($this->card($request, $model->refresh()));
    }

    /** «Change the method» (§8.5): an online one gets its attempt; the buyer pays by the link. */
    public function switchMethod(Request $request, int $order, Payments $payments): JsonResponse
    {
        $model = $this->order($order);
        $method = $this->methods->find((string) $request->input('method'));

        if ($method === null) {
            throw ValidationException::withMessages(['method' => [__('webx-payments::panel.errors.unknown_method')]]);
        }

        try {
            $payments->switchMethod($model, $method, 'panel');
        } catch (BeginRefused $refused) {
            throw ValidationException::withMessages(['method' => [$refused->getMessage()]]);
        }

        return ApiResponse::data($this->card($request, $model->refresh()));
    }

    /** @return array<string, mixed> */
    private function card(Request $request, Order $order): array
    {
        return $this->tab->card($order, $request->user());
    }

    /**
     * What a capture or a release would move, for the dialog's line of consequences. A capture
     * that does not cover what the order owes sets no milestone «paid» (decision of 03.11.2026):
     * `covers` false, and `left` — what stays unpaid.
     *
     * @return array<string, mixed>
     */
    private function preview(Payment $payment, int $amount, ?Order $order = null): array
    {
        $held = (int) ($payment->authorized_amount ?? $payment->amount);
        $owes = $order !== null && $order->paid_at === null ? max(0, Balance::stored($order)) : 0;
        $left = max(0, $owes - $amount);

        return [
            'covers' => $left === 0,
            'owes' => $owes,
            'owes_formatted' => $this->money->format($owes, $payment->currency),
            'left' => $left,
            'left_formatted' => $this->money->format($left, $payment->currency),
            'dry_run' => true,
            'amount' => $amount,
            'amount_formatted' => $this->money->format($amount, $payment->currency),
            'held' => $held,
            'held_formatted' => $this->money->format($held, $payment->currency),
            'released' => $held - $amount,
            'released_formatted' => $this->money->format($held - $amount, $payment->currency),
        ];
    }

    /** A capture's amount as typed, «120.50» or «120,50» in the order's currency; empty — all of the hold. */
    private function amount(?string $typed, string $currency): ?int
    {
        $typed = $typed !== null ? str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim($typed)) : '';

        if ($typed === '') {
            return null;
        }

        try {
            return GatewayAmount::fromDecimal($typed, $currency);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['amount' => [__('webx-payments::panel.errors.amount')]]);
        }
    }

    /**
     * @param  Closure(): SettleResult  $action
     *
     * @throws ValidationException the base or the gateway refused, or the gateway did not answer
     */
    private function hold(Closure $action): SettleResult
    {
        try {
            return $action();
        } catch (HoldRefused $refused) {
            throw ValidationException::withMessages([$refused->reason === HoldRefused::AMOUNT || $refused->reason === HoldRefused::TOO_MUCH || $refused->reason === HoldRefused::PARTIAL ? 'amount' : 'payment' => [$refused->getMessage()]]);
        } catch (GatewayRefused) {
            throw ValidationException::withMessages(['payment' => [__('webx-payments::panel.errors.void_refused')]]);
        } catch (GatewayUnavailable) {
            throw ValidationException::withMessages(['payment' => [__('webx-payments::panel.errors.gateway_unavailable')]]);
        }
    }

    private function attempt(Order $order, int $payment): Payment
    {
        /** @var Payment */
        return Payment::query()->where('order_id', $order->id)->findOrFail($payment);
    }

    private function order(int $id): Order
    {
        return Order::query()->findOrFail($id);
    }
}
