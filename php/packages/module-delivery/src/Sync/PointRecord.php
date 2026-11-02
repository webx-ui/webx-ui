<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

/**
 * A point as the carrier has it (§7.2): a branch, a locker or a kind the carrier registered.
 * Named arguments; new fields come last and optional.
 */
final readonly class PointRecord
{
    /**
     * @param  array<string, string>|null  $titles  locale → title
     * @param  array<string, string>|null  $addresses  locale → address
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $externalId,
        public string $kind,
        public string $localityExternalId,
        public string $address,
        public ?string $number = null,
        public ?string $title = null,
        public ?array $titles = null,
        public ?array $addresses = null,
        public ?string $postcode = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?Schedule $schedule = null,
        public ?Limits $limits = null,
        public ?string $phone = null,
        public bool $active = true,
        public array $meta = [],
        /**
         * Who alone may pick the point up, when not everybody: `residents` — a locker in a block of
         * flats for those who live there. Shown beside the point, and such points go after the
         * others in the list; null — open to all.
         */
        public ?string $restriction = null,
    ) {}
}
