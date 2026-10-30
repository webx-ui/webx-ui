<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use WebxUi\Orders\Models\Order;

/**
 * The order's money in one answer (spec §36): what it should cost the buyer in money now, what
 * came in, what went back, and so what is still due or over. The panel, the agent and the events
 * take it counted from here, never by a formula of their own.
 *
 * `orders.balance` is the stored copy of {@see stored()} for the filters and the sort: only the
 * method of `Orders` that changes one of its terms writes it, in the same update.
 */
final readonly class Balance
{
    public function __construct(
        /** `payable`, or 0 once cancelled: a cancelled paid order shows «give back N» by itself. */
        public int $owed,
        public int $paid,
        public int $refunded,
        /** > 0 — the buyer still owes it. */
        public int $due,
        /** > 0 — the shop owes the buyer this money back. */
        public int $over,
        /** The tenders that are more than the order costs now (§37.5) — only an edit makes them. */
        public int $tendersOver,
        public string $currency,
    ) {}

    public static function of(Order $order): self
    {
        $owed = self::owed($order);
        $balance = self::stored($order);

        return new self(
            owed: $owed,
            paid: $order->paid_total,
            refunded: $order->refunded_total,
            due: max(0, $balance),
            over: max(0, -$balance),
            tendersOver: $order->cancelled_at === null ? max(0, $order->tenders_total - $order->total) : 0,
            currency: $order->currency,
        );
    }

    /**
     * `due − over` (§36.1, decision of 30.10.2026): > 0 the buyer owes, < 0 the shop does. A
     * refund is never a debt: it gives back money that came in, so only `paid_total` stands
     * against `payable`, and the refunds only lower what is over.
     */
    public static function stored(Order $order): int
    {
        $owed = self::owed($order);
        $due = $order->cancelled_at !== null ? 0 : max(0, $order->payable - $order->paid_total);
        $over = max(0, $order->paid_total - $order->refunded_total - $owed);

        return $due - $over;
    }

    public static function owed(Order $order): int
    {
        return $order->cancelled_at !== null ? 0 : $order->payable;
    }

    /**
     * How the balance reads in one word (§36.1): `topup` — paid, and owes more; `unpaid` — the
     * ordinary «to pay», nothing came in yet; `over` — money to give back; null — settled.
     */
    public function kind(): ?string
    {
        return match (true) {
            $this->over > 0 => 'over',
            $this->due > 0 && $this->paid > 0 => 'topup',
            $this->due > 0 => 'unpaid',
            default => null,
        };
    }

    /**
     * The most money a refund may give back now (§36.2): a payment over the order's cost after an
     * edit is the buyer's too, so it is `max(payable, paid_total)`; an order never marked paid
     * keeps `payable`.
     */
    public static function refundable(Order $order): int
    {
        return max(0, max($order->payable, $order->paid_total) - $order->refunded_total);
    }
}
