<?php

declare(strict_types=1);

namespace WebxUi\Payments\Panel;

use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Payments\Contracts\CapturesPayments;
use WebxUi\Payments\GatewayAmount;
use WebxUi\Payments\Instructions;
use WebxUi\Payments\Kind;
use WebxUi\Payments\Methods;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\PaymentSnapshot;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\SettleResult;

/**
 * The tab «Payment» of the order's card (spec §13.4) as data — and the agent's `payments_get` of an
 * order (§16), so the two never tell two stories: the method with its bank details, the state, the
 * attempts with their events of the exchange by time, how it was paid and the actions the reader
 * may take. Events are lines (§20.7): no header and no body, whatever the reader may open.
 */
final readonly class OrderPaymentCard
{
    public function __construct(
        private AttemptRows $rows,
        private Money $money,
        private PaymentLinks $links,
        private Methods $methods,
        private EventNames $names,
        private MethodTypes $types,
    ) {}

    /** @return array<string, mixed> */
    public function card(Order $order, mixed $user): array
    {
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
            // Money held (§9.5): captured — whole, or a part where the gateway takes one — or let go.
            'can_capture' => $manage && $payment->status === PaymentStatus::Authorized && $this->holds($payment) !== null,
            'partial' => $payment->status === PaymentStatus::Authorized && ($this->holds($payment)?->capturesPartially() ?? false),
            // The sum to capture as the shop writes its money — «181,90» beside «181,90 €», not «181.90».
            'amount_decimal' => $payment->status === PaymentStatus::Authorized
                ? str_replace('.', $this->money->decimalSign($payment->currency), GatewayAmount::toDecimal((int) ($payment->authorized_amount ?? $payment->amount), $payment->currency))
                : null,
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
            // The form's label of the purpose explains its {number}; the order shows it filled in, so the buyer's word.
            $label = $field === 'purpose' ? 'webx-payments::buyer.instructions.purpose' : 'webx-payments::methods.bank_transfer.settings.'.$field;
            $lines[] = ['label' => (string) __($label), 'text' => $text];
        }

        return $lines;
    }

    /** The type of a held attempt that can capture it, or null. */
    private function holds(Payment $payment): ?CapturesPayments
    {
        $type = $this->types->has($payment->type) ? $this->types->get($payment->type) : null;

        return $type instanceof CapturesPayments ? $type : null;
    }

    /**
     * @param  list<Payment>|null  $payments
     */
    public function unfinished(Order $order, ?array $payments = null): ?Payment
    {
        $list = $payments ?? Payment::query()->where('order_id', $order->id)->orderBy('id')->get()->all();

        foreach (array_reverse($list) as $payment) {
            if (in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Authorized], true)) {
                return $payment;
            }
        }

        return null;
    }
}
