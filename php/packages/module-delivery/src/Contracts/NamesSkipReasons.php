<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Contracts;

/**
 * A carrier that says in words why its pages left records out — the reasons a satellite puts in
 * `Page::$skipped` (`novaposhta_service_point`, …), which the base knows nothing about.
 *
 * Optional on top of {@see SyncsDirectories}, so that an older satellite keeps working: a reason
 * nobody names is still said in words on the screen of the carriers («Skipped, for another
 * reason: 12»), never by its key.
 */
interface NamesSkipReasons
{
    /** «Skipped, service points: 12» in this language; null — the base's own words. */
    public function skipReason(string $reason, int $count, string $locale): ?string;
}
