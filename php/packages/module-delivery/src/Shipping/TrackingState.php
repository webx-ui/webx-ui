<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Shipping;

use DateTimeImmutable;

/** What the carrier says of one parcel (§11.3), in the base's statuses and the carrier's own words. */
final readonly class TrackingState
{
    public bool $final;

    public function __construct(
        public ShipmentStatus $status,
        /** When it happened, as the carrier says; null — now. */
        public ?DateTimeImmutable $at = null,
        /** The carrier's words: «Arrived at branch 12». */
        public ?string $text = null,
        /** Final without being one of the final statuses: the carrier stopped tracking it. */
        ?bool $final = null,
    ) {
        $this->final = $final ?? $status->final();
    }
}
