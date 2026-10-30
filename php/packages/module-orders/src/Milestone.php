<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * The facts of an order's life the system relies on (spec §5.1): what events, neighbours and
 * integrations work with, rather than with the names of statuses a shop gave its stages.
 *
 * A closed list, in the order of the card's timeline. Each is set once and never taken back
 * (decision 24): a mistaken payment is mended by a refund or a cancellation, not by unmarking.
 */
enum Milestone: string
{
    case Placed = 'placed';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Received = 'received';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Whether moving an order into a status may set this one (§3, decision 13). The first two
     * come only with the order itself, and «refunded» only with a refund — it has an amount.
     */
    public function settableByStatus(): bool
    {
        return match ($this) {
            self::Paid, self::Received, self::Cancelled => true,
            self::Placed, self::AwaitingPayment, self::Refunded => false,
        };
    }

    /** The column on `orders` that keeps a copy of it for the filters of the list. */
    public function column(): string
    {
        return match ($this) {
            self::Placed => 'placed_at',
            self::AwaitingPayment => 'awaiting_payment',
            self::Paid => 'paid_at',
            self::Received => 'received_at',
            self::Cancelled => 'cancelled_at',
            self::Refunded => 'refunded_at',
        };
    }

    /** In the language of the panel, or the one asked for. */
    public function label(?string $locale = null): string
    {
        return (string) __("webx-orders::module.milestones.{$this->value}", [], $locale);
    }

    /**
     * The ones a status may set, for the choice in the statuses screen.
     *
     * @return list<self>
     */
    public static function settable(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $milestone): bool => $milestone->settableByStatus()));
    }
}
