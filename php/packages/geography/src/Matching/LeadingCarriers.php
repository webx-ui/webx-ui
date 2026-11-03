<?php

declare(strict_types=1);

namespace WebxUi\Geography\Matching;

/**
 * Which carrier leads a country (§4.4, decision 26): its unmatched localities become shared
 * ones, everybody else's go to the binding queue.
 *
 * The setting is `geography.countries.<code>.source`. The library keeps it in its config
 * ({@see ConfigLeadingCarriers}); the delivery module binds its own implementation over the
 * site settings, where the panel shows and changes it.
 */
interface LeadingCarriers
{
    /** The carrier key leading the country, or null when none is set yet. */
    public function leader(string $country): ?string;

    /**
     * The leader of the country, making `$carrier` it when there is none: the first carrier to
     * sync a country leads it until someone picks another.
     */
    public function claim(string $country, string $carrier): string;
}
