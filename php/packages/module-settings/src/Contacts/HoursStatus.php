<?php

declare(strict_types=1);

namespace WebxUi\Settings\Contacts;

use Carbon\CarbonImmutable;

/**
 * Open or closed at `$at`, in the site's zone. Open: until `until`, or for good when it is null
 * (24/7). Closed: opens at `next`, or not within the fortnight `Hours` looks ahead when it is
 * null; `dayOff` — today has no hours at all, "Closed today" rather than "Closed, opens at 14:00".
 */
final class HoursStatus
{
    public function __construct(
        public readonly bool $open,
        public readonly CarbonImmutable $at,
        public readonly ?CarbonImmutable $until,
        public readonly ?CarbonImmutable $next,
        public readonly bool $dayOff,
    ) {}

    /**
     * What to say, as one word: `open`, `always`; closed — `today` (opens later today),
     * `tomorrow`, `later`, `closed` (nothing ahead), each of the last three with `off-` before it
     * when today is a day off.
     */
    public function kind(): string
    {
        if ($this->open) {
            return $this->until === null ? 'always' : 'open';
        }

        $prefix = $this->dayOff ? 'off-' : '';

        if ($this->next === null) {
            return $this->dayOff ? 'off' : 'closed';
        }

        // By the date, not by hours between: the night the clocks change has 23 or 25 of them.
        $date = $this->next->format('Y-m-d');

        return match (true) {
            $date === $this->at->format('Y-m-d') => 'today',
            $date === $this->at->addDay()->format('Y-m-d') => $prefix.'tomorrow',
            default => $prefix.'later',
        };
    }
}
