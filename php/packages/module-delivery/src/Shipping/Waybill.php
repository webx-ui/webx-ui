<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Shipping;

/** What the carrier answered to a waybill (§11.2): its number and, when it says, its price. */
final readonly class Waybill
{
    /**
     * @param  array<string, mixed>  $meta  the satellite's own: the label's address, the carrier's internal id
     */
    public function __construct(
        public string $number,
        /** What the carrier charges, in minor units; null — it did not say. */
        public ?int $cost = null,
        public ShipmentStatus $status = ShipmentStatus::Created,
        public array $meta = [],
    ) {}
}
