<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use WebxUi\Geography\Matching\LocalityRecord;

/** One page of a carrier's directory (§7.1). */
final readonly class Page
{
    /**
     * @param  list<LocalityRecord|PointRecord|StreetRecord>  $records
     * @param  string|null  $next  the cursor of the next page; null — this is the last one
     * @param  array<string, list<string>>  $skipped  what the carrier gave and the satellite left out, by
     *                                                why — the external ids; the run counts them in its warnings
     */
    public function __construct(
        public array $records,
        public ?string $next = null,
        public array $skipped = [],
    ) {}
}
