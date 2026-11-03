<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Shipping;

/**
 * Where a parcel is (§11.3): a closed list the satellites translate their carrier's statuses into,
 * so that the order's tab, the agent and the milestone «received» need know no carrier.
 */
enum ShipmentStatus: string
{
    case Created = 'created';
    case InTransit = 'in_transit';
    /** At the pick-up point, waiting for the buyer. */
    case AtPoint = 'at_point';
    case OutForDelivery = 'out_for_delivery';
    case Received = 'received';
    case Refused = 'refused';
    case Returning = 'returning';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    /** Nothing follows: the tracking does not ask about it again. */
    public function final(): bool
    {
        return match ($this) {
            self::Received, self::Returned, self::Cancelled => true,
            default => false,
        };
    }

    /**
     * When to ask again: soon while the buyer is about to take it — at the point or with the
     * courier — and every two hours on the way.
     */
    public function nextCheckMinutes(): int
    {
        return match ($this) {
            self::AtPoint, self::OutForDelivery => 30,
            default => 120,
        };
    }

    public function label(?string $locale = null): string
    {
        return (string) trans('webx-delivery::delivery.shipments.statuses.'.$this->value, [], $locale);
    }
}
