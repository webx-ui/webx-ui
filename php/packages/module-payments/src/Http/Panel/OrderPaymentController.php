<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http\Panel;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Payments\BeginRefused;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Instructions;
use WebxUi\Payments\Kind;
use WebxUi\Payments\MarkRefused;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Panel\AttemptRows;
use WebxUi\Payments\Panel\EventNames;
use WebxUi\Payments\Panel\EventRows;
use WebxUi\Payments\Panel\PaymentsSection;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentSnapshot;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\ProblemRefused;
use WebxUi\Payments\SettleResult;

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
        private readonly AttemptRows $rows,
        private readonly Money $money,
        private readonly PaymentLinks $links,
        private readonly Methods $methods,
        private readonly EventNames $names,
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
        $payment = $this->unfinished($model);

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
        $user = $request->user();
        $manage = $user instanceof HasPermissions && $user->hasPermission(OrdersModule::MANAGE);
        $bodies = $user instanceof HasPermissions && $user->hasPermission(PaymentsSection::EXCHANGE);
        $locale = app()->getLocale();
        $balance = Balance::of($order);
        $block = is_array($order->payment) ? $order->payment : null;

        $payments = Payment::query()->where('order_id', $order->id)->orderBy('id')->get();
        $lines = $this->names->apply(EventRows::query()->where('order_id', $order->id)->orderBy('id')->limit(500)->get()
            ->map(static fn (PaymentGatewayEvent $event): array => EventRows::line($event))->values()->all(), $user);
        $events = collect($lines)->groupBy(static fn (array $line): int => (int) $line['payment_id']);
        $named = collect($this->rows->named($this->rows->lines($payments), $user))->keyBy('id');

        $attempts = $payments->map(fn (Payment $payment): array => [
            ...$named[$payment->id],
            'events' => ($events[$payment->id] ?? collect())->values()->all(),
            'can_resolve' => $manage && $payment->problem !== null && ! isset(($payment->meta ?? [])['resolved_at'])
                && ! in_array($payment->problem, SettleResult::HOLD_PROBLEMS, true),
        ])->reverse()->values()->all();

        $closed = $order->cancelled_at !== null;
        $paid = $order->paid_at !== null;
        $held = $payments->contains(static fn (Payment $payment): bool => $payment->status === PaymentStatus::Authorized);
        $online = ($block['kind'] ?? null) === Kind::Online->value;

        return [
            'order_id' => $order->id,
            'number' => $order->number,
            'currency' => $order->currency,
            'block' => $block === null ? null : [
                'method' => $block['method'] ?? null,
                'type' => $block['type'] ?? null,
                'kind' => $block['kind'] ?? null,
                'title' => PaymentSnapshot::summary($block, $locale),
                'instructions' => $this->instructions($block, (string) $order->number, $locale),
            ],
            'state' => match (true) {
                $closed => 'cancelled',
                $paid => 'paid',
                $held => 'held',
                $order->payment_failed_at !== null && $order->awaiting_payment => 'failed',
                $order->awaiting_payment => 'awaiting',
                default => 'none',
            },
            'paid_at' => $order->paid_at?->toIso8601String(),
            'due' => $balance->due,
            'due_formatted' => $this->money->format($balance->due, $order->currency),
            'paid' => $balance->paid,
            'paid_formatted' => $this->money->format($balance->paid, $order->currency),
            // A paid order whose items grew owes the difference (decision 29).
            'top_up' => $paid && $balance->due > 0,
            'attempts' => $attempts,
            'other_events' => ($events[0] ?? collect())->values()->all(),
            // How it was paid, for a card without a method of its own (a mark of an order placed
            // without one): the attempt that took the money — method, document, date and who.
            'paid_by' => $paid ? $this->paidBy($payments->all(), $named->all()) : null,
            'bodies' => $bodies,
            'can_manage' => $manage,
            'pay_link' => $manage && ! $closed && $balance->due > 0 && ! $held ? ($this->links->page($order) ?: null) : null,
            'can_mark_paid' => $manage && ! $closed && ! $paid && ! $online,
            'can_check' => $manage && $this->unfinished($order, $payments->all()) !== null,
            'can_switch' => $manage && ! $closed && $balance->due > 0 && ! $held,
            'methods' => $manage && ! $closed && $balance->due > 0 && ! $held ? $this->choices($order, $balance->due, $block['method'] ?? null) : [],
        ];
    }

    /**
     * The latest attempt that took money.
     *
     * @param  list<Payment>  $payments
     * @param  array<int, array<string, mixed>>  $lines  by the attempt's id
     * @return array<string, mixed>|null
     */
    private function paidBy(array $payments, array $lines): ?array
    {
        foreach (array_reverse($payments) as $payment) {
            if ($payment->status === PaymentStatus::Paid) {
                $line = $lines[$payment->id];

                return [
                    'id' => $payment->id,
                    'method_title' => $line['method_title'],
                    'reference' => $line['reference'],
                    'paid_at' => $line['paid_at'],
                    'amount_formatted' => $line['paid_amount_formatted'] ?? $line['amount_formatted'],
                    'source' => $payment->source,
                    'admin_name' => $line['admin_name'],
                    'gateway_ref' => $payment->gateway_ref,
                ];
            }
        }

        return null;
    }

    /**
     * The enabled methods a manager may switch the order to, each available or with its reason (§7.2).
     *
     * @return list<array<string, mixed>>
     */
    private function choices(Order $order, int $due, mixed $current): array
    {
        $context = new PaymentContext($due, (string) $order->currency, (string) $order->locale, null, Purpose::Order);
        $choices = [];

        foreach ($this->methods->enabled() as $method) {
            if ($method->key === $current) {
                continue;
            }

            $availability = $this->methods->check($method, $context);
            $choices[] = [
                'key' => $method->key,
                'title' => $this->methods->option($method, $context)->title,
                'available' => $availability->available,
                'reason' => $availability->message(app()->getLocale()),
            ];
        }

        return $choices;
    }

    /**
     * The details of a transfer with their labels.
     *
     * @param  array<string, mixed>  $block
     * @return list<array{label: string, text: string}>
     */
    private function instructions(array $block, string $number, string $locale): array
    {
        $lines = [];

        foreach (Instructions::of($block, $number, $locale) ?? [] as $field => $text) {
            $lines[] = ['label' => (string) __('webx-payments::methods.bank_transfer.settings.'.$field), 'text' => $text];
        }

        return $lines;
    }

    /**
     * @param  list<Payment>|null  $payments
     */
    private function unfinished(Order $order, ?array $payments = null): ?Payment
    {
        $list = $payments ?? Payment::query()->where('order_id', $order->id)->orderBy('id')->get()->all();

        foreach (array_reverse($list) as $payment) {
            if (in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Authorized], true)) {
                return $payment;
            }
        }

        return null;
    }

    private function order(int $id): Order
    {
        return Order::query()->findOrFail($id);
    }
}
