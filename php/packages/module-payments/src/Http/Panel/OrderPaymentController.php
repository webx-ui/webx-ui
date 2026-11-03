<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http\Panel;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\BeginRefused;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\MarkRefused;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Panel\OrderPaymentCard;
use WebxUi\Payments\Payments;
use WebxUi\Payments\ProblemRefused;

/**
 * The tab «Payment» of the order's card (spec §13.4), patched onto `orders.show`: the method of the
 * order with its bank details, the state, the attempts with their events of the exchange by time,
 * and the actions — which are the order's, so `orders.manage` (§13.1): «Mark as paid», «Check with
 * the gateway», «Change the method», «Payment link», «Problem resolved».
 *
 * The tab reads for `payments.view`; the bodies of the events open in the journal's own event, for
 * `payments.exchange`.
 */
final class OrderPaymentController
{
    public function __construct(
        private readonly OrderPaymentCard $tab,
        private readonly Methods $methods,
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

    private function order(int $id): Order
    {
        return Order::query()->findOrFail($id);
    }
}
