<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta;

/**
 * How the pass of the streets goes (§6.4, §6.6), from `webx-delivery-novaposhta.sync`: when it
 * runs, how much one pull may ask of the carrier, and whether it walks every town or a sample.
 */
final readonly class StreetsPass
{
    /** The towns a read-only key walks unless told otherwise: at most 20 (decision 19). */
    public const int READ_ONLY_SAMPLE = 20;

    /**
     * @param  string  $cron  the satellite's schedule; `manual` — by hand only
     * @param  int  $requests  at most this many `getStreet` a pull
     * @param  float  $seconds  no new request after this long a pull; the first one always goes
     * @param  int|null  $sample  only this many first towns with a courier; null — all of them
     */
    public function __construct(
        public string $cron = NovaPoshtaCarrier::WEEKLY,
        public int $requests = 20,
        public float $seconds = 20.0,
        public ?int $sample = null,
    ) {}
}
