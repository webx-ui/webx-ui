<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrderCancelledAlready;
use WebxUi\Orders\Orders;
use WebxUi\Payments\Contracts\CapturesPayments;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeDecision;
use WebxUi\Payments\Exchange\ExchangeEntry;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\ExchangeSecrets;
use WebxUi\Payments\Exchange\Outcome;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\Types\BankTransfer;

/**
 * The payments of orders (spec §8–§12): a new online attempt (`begin()`, §8.2) and a manager's
 * «Mark as paid» of an order paid by transfer or on receipt (§12.2). The callback, the hold and the
 * poll come with the next stages.
 */
final readonly class Payments
{
    public function __construct(
        private Orders $orders,
        private ExchangeJournal $journal,
        private ConnectionInterface $db,
        private GatewayCalls $calls,
    ) {}

    /**
     * A new attempt for an order (§8.2, §8.5): a row `created` for what the order owes now
     * (decision 29), with no network — the gateway is called on GET /pay/go/{uuid}, where a
     * failure has a page of its own. Attempts that never went to the gateway are closed first;
     * the ones the gateway knows are closed through it with the poll (§9.4).
     *
     * @param  string  $source  'checkout' | 'pay-page' | 'panel' | 'mcp' | 'api'
     *
     * @throws BeginRefused
     */
    public function begin(Order $order, Method $method, string $source): Payment
    {
        $type = $this->calls->type($method->type) ?? throw new BeginRefused(BeginRefused::NOT_ONLINE);
        // An online type without its gateway is a satellite's mistake: loud here, not at the gateway.
        $this->calls->gateway($type);

        return $this->db->transaction(function () use ($order, $method, $type, $source): Payment {
            $fresh = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($fresh->cancelled_at !== null) {
                throw new BeginRefused(BeginRefused::CANCELLED);
            }

            $due = $this->orders->balance($fresh)->due;

            if ($due <= 0) {
                throw new BeginRefused(BeginRefused::NOTHING_DUE);
            }

            Payment::query()
                ->where('order_id', $fresh->id)
                ->where('status', PaymentStatus::Created->value)
                ->update(['status' => PaymentStatus::Cancelled->value, 'failure_reason' => 'replaced by a new attempt', 'updated_at' => CarbonImmutable::now()]);

            return Payment::query()->create([
                'order_id' => $fresh->id,
                'method_key' => $method->key,
                'type' => $type->key(),
                'amount' => $due,
                'currency' => $fresh->currency,
                'status' => PaymentStatus::Created,
                // A hold only where the type can capture it later (§9.5).
                'hold' => $type instanceof CapturesPayments && (bool) ($method->settings['hold'] ?? false),
                'source' => $source,
            ]);
        });
    }

    /**
     * The money is in (§12.2): an attempt `paid` for what the order owes, from the manager's
     * source, and the order's milestone «paid» with the document's number — in one transaction —
     * then an event `internal/manual` in the exchange journal, with no body: who and when.
     * The manager does not type an amount: no partial payment by transfer in v1 (decision 23).
     *
     * @param  string  $source  'panel' | 'mcp' | 'api'
     *
     * @throws MarkRefused the order is paid already, or cancelled
     */
    public function markPaid(
        Order $order,
        string $source,
        ?string $reference = null,
        ?CarbonInterface $paidAt = null,
        ?string $note = null,
    ): Payment {
        $paidAt = $paidAt !== null ? CarbonImmutable::instance($paidAt) : CarbonImmutable::now();
        $reference = self::text($reference, 190);

        $payment = $this->db->transaction(function () use ($order, $source, $reference, $paidAt, $note): Payment {
            $fresh = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($fresh->cancelled_at !== null) {
                throw new MarkRefused(MarkRefused::CANCELLED);
            }

            if ($fresh->paid_at !== null) {
                throw new MarkRefused(MarkRefused::PAID);
            }

            $due = $this->orders->balance($fresh)->due;

            try {
                $mark = $this->orders->mark($fresh, Milestone::Paid, $source, $paidAt, $reference, $note);
            } catch (OrderCancelledAlready) {
                throw new MarkRefused(MarkRefused::CANCELLED);
            }

            if ($mark->alreadyMarked()) {
                throw new MarkRefused(MarkRefused::PAID);
            }

            $block = is_array($fresh->payment) ? $fresh->payment : [];

            return Payment::query()->create([
                'order_id' => $fresh->id,
                'method_key' => is_string($block['method'] ?? null) ? $block['method'] : BankTransfer::METHOD,
                'type' => is_string($block['type'] ?? null) ? $block['type'] : BankTransfer::KEY,
                'amount' => $due,
                'currency' => $fresh->currency,
                'status' => PaymentStatus::Paid,
                'paid_amount' => $due,
                'paid_at' => $paidAt,
                'source' => $source,
                'admin_id' => $mark->adminId,
                'meta' => $reference !== null ? ['reference' => $reference] : null,
            ]);
        });

        $this->journal($payment, $source, $reference);

        return $payment;
    }

    /** The manual mark in the journal (§20.1): no bodies, the decision only. */
    private function journal(Payment $payment, string $source, ?string $reference): void
    {
        $secrets = ExchangeSecrets::none();
        $id = $this->journal->record(new ExchangeEntry(
            kind: ExchangeKind::Manual,
            trace: ExchangeContext::newTrace(),
            methodKey: $payment->method_key,
            paymentId: $payment->id,
            orderId: $payment->order_id,
            source: $source,
            startedAt: CarbonImmutable::now(),
            durationMs: 0,
        ), $secrets);

        if ($id === null) {
            return;
        }

        $who = $payment->admin_id !== null ? "admin #{$payment->admin_id}" : $source;

        $this->journal->decide($id, new ExchangeDecision(
            outcome: Outcome::Applied,
            paymentId: $payment->id,
            orderId: $payment->order_id,
            statusBefore: null,
            statusAfter: PaymentStatus::Paid->value,
            message: "marked paid by {$who}".($reference !== null ? ", document {$reference}" : ''),
        ), $secrets);
    }

    private static function text(?string $text, int $max): ?string
    {
        $text = $text !== null ? trim($text) : null;

        return $text === null || $text === '' ? null : mb_substr($text, 0, $max);
    }
}
