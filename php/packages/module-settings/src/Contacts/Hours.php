<?php

declare(strict_types=1);

namespace WebxUi\Settings\Contacts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * When the site is open (WIDGETS §12.3): a week of intervals, dates that differ from it, and the
 * time zone they are in — the site's, never the visitor's: "open until 19:00" is about the shop,
 * and a visitor abroad must read the shop's hours.
 *
 * An interval is minutes from the start of its day, `[540, 1140]` for 9:00–19:00. One that
 * closes at or before it opens runs past midnight — 22:00–02:00 is `[1320, 1560]` — and
 * 00:00–00:00 is the whole day. A day with no interval is a day off; a date in `exceptions`
 * replaces its weekday (a holiday closed, or a short day).
 *
 * @phpstan-type Interval array{0: int, 1: int}
 * @phpstan-type Exception array{intervals: list<Interval>, label: string|null}
 */
final class Hours
{
    /** ISO weekdays, Monday first: the order the panel lists them in and the table prints them in. */
    public const array DAYS = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];

    /** How far ahead "opens on …" looks: past a fortnight of closed days nobody waits for a date. */
    public const int HORIZON = 14;

    private const int DAY = 1440;

    /**
     * @param  array<int, list<Interval>>  $week  by ISO weekday
     * @param  array<string, Exception>  $exceptions  by date, `Y-m-d`
     */
    public function __construct(
        private readonly array $week,
        private readonly array $exceptions,
        private readonly DateTimeZone $timezone,
    ) {}

    /**
     * From the settings as the panel stores them: rows of `days`, `opens`, `closes`, and rows of
     * `date`, `closed`, `opens`, `closes`, `label`. A row that is not whole is skipped rather
     * than read as "closed": half a row is a row still being typed.
     */
    public static function fromSettings(mixed $rows, mixed $exceptions, string $timezone): self
    {
        $week = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $interval = is_array($row) ? self::interval($row['opens'] ?? null, $row['closes'] ?? null) : null;

            if ($interval === null) {
                continue;
            }

            foreach ((array) ($row['days'] ?? []) as $day) {
                if (is_string($day) && isset(self::DAYS[$day])) {
                    $week[self::DAYS[$day]][] = $interval;
                }
            }
        }

        $dates = [];

        foreach (is_array($exceptions) ? $exceptions : [] as $row) {
            $date = is_array($row) && is_string($row['date'] ?? null) ? substr($row['date'], 0, 10) : null;

            if ($date === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                continue;
            }

            $closed = ($row['closed'] ?? true) !== false;
            $interval = $closed ? null : self::interval($row['opens'] ?? null, $row['closes'] ?? null);

            if (! $closed && $interval === null) {
                continue;
            }

            $label = is_string($row['label'] ?? null) && trim($row['label']) !== '' ? trim($row['label']) : null;
            $dates[$date] = [
                'intervals' => [...($dates[$date]['intervals'] ?? []), ...($interval === null ? [] : [$interval])],
                'label' => $label ?? ($dates[$date]['label'] ?? null),
            ];
        }

        foreach ($week as $day => $intervals) {
            $week[$day] = self::sorted($intervals);
        }

        foreach ($dates as $date => $exception) {
            $dates[$date]['intervals'] = self::sorted($exception['intervals']);
        }

        ksort($dates);

        return new self($week, $dates, self::zone($timezone));
    }

    /** A zone the site names, or the application's when it names none or one PHP does not know. */
    public static function zone(?string $name): DateTimeZone
    {
        foreach ([$name, (string) config('app.timezone', 'UTC'), 'UTC'] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                try {
                    return new DateTimeZone($candidate);
                } catch (Throwable) {
                    // The next one.
                }
            }
        }

        return new DateTimeZone('UTC');
    }

    public function isEmpty(): bool
    {
        return $this->week === [];
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /** @return array<int, list<Interval>> */
    public function week(): array
    {
        return $this->week;
    }

    /** @return array<string, Exception> */
    public function exceptions(): array
    {
        return $this->exceptions;
    }

    /**
     * The intervals of one date: its exception when it has one, its weekday otherwise.
     *
     * @return list<Interval>
     */
    public function on(DateTimeInterface $date): array
    {
        $local = CarbonImmutable::instance($date)->setTimezone($this->timezone);

        return $this->exceptions[$local->format('Y-m-d')]['intervals'] ?? $this->week[$local->isoWeekday()] ?? [];
    }

    public function openNow(?DateTimeInterface $at = null): bool
    {
        return $this->status($at)->open;
    }

    /**
     * Open or closed at a moment, and until when or from when — what "Open until 19:00" and
     * "Closed, opens tomorrow at 9:00" are made of.
     */
    public function status(?DateTimeInterface $at = null): HoursStatus
    {
        $now = CarbonImmutable::instance($at ?? CarbonImmutable::now())->setTimezone($this->timezone);
        $today = $now->startOfDay();
        $minute = $now->hour * 60 + $now->minute;

        $until = null;

        foreach ($this->on($today) as [$opens, $closes]) {
            if ($opens <= $minute && $minute < $closes) {
                $until = self::at($today, $closes);
            }
        }

        // Last night's interval still running: 22:00–02:00 at one in the morning.
        foreach ($this->on($today->subDay()) as [$opens, $closes]) {
            if ($closes > self::DAY && $minute < $closes - self::DAY) {
                $until = self::at($today->subDay(), $closes);
            }
        }

        if ($until !== null) {
            return new HoursStatus(true, $now, $this->extended($until), null, false);
        }

        $dayOff = $this->on($today) === [];

        for ($ahead = 0; $ahead <= self::HORIZON; $ahead++) {
            $day = $today->addDays($ahead);

            foreach ($this->on($day) as [$opens]) {
                if ($ahead > 0 || $opens > $minute) {
                    return new HoursStatus(false, $now, null, self::at($day, $opens), $dayOff);
                }
            }
        }

        return new HoursStatus(false, $now, null, null, $dayOff);
    }

    /**
     * The week folded into rows of days that keep the same hours: Mon–Fri 9:00–19:00, Sat, Sun
     * closed — the table of the contacts page and the dropdown under the status.
     *
     * @return list<array{days: list<int>, intervals: list<Interval>}>
     */
    public function rows(): array
    {
        $rows = [];

        foreach (self::DAYS as $day) {
            $intervals = $this->week[$day] ?? [];
            $last = array_key_last($rows);

            if ($last !== null && $rows[$last]['intervals'] === $intervals) {
                $rows[$last]['days'][] = $day;
            } else {
                $rows[] = ['days' => [$day], 'intervals' => $intervals];
            }
        }

        return $rows;
    }

    /**
     * The dates that differ from the week, from a day on, for so many days — what the table lists
     * under the week so that nobody comes on a holiday.
     *
     * @return array<string, Exception>
     */
    public function upcoming(?DateTimeInterface $from = null, int $days = 30): array
    {
        $first = CarbonImmutable::instance($from ?? CarbonImmutable::now())->setTimezone($this->timezone)->format('Y-m-d');
        $last = CarbonImmutable::instance($from ?? CarbonImmutable::now())->setTimezone($this->timezone)->addDays($days)->format('Y-m-d');

        return array_filter($this->exceptions, static fn (string $date): bool => $date >= $first && $date <= $last, ARRAY_FILTER_USE_KEY);
    }

    /**
     * "Until" that runs into the next day's opening at midnight is not the end: 24/7 is open
     * until never, and 18:00–24:00 then 00:00–02:00 is open until two.
     */
    private function extended(CarbonImmutable $until): ?CarbonImmutable
    {
        for ($days = 0; $days < 8; $days++) {
            $day = $until->startOfDay();
            $minute = $until->hour * 60 + $until->minute;
            $next = null;

            foreach ($this->on($day) as [$opens, $closes]) {
                if ($opens <= $minute && $minute < $closes) {
                    $next = self::at($day, $closes);
                }
            }

            if ($next === null || $next->lessThanOrEqualTo($until)) {
                return $until;
            }

            $until = $next;
        }

        return null;
    }

    /**
     * The moment of a minute of a day by the wall clock: 9:00 is nine o'clock on the day the
     * clocks change too, which 540 minutes after midnight is not.
     */
    private static function at(CarbonImmutable $day, int $minutes): CarbonImmutable
    {
        return $day->addDays(intdiv($minutes, self::DAY))->setTime(intdiv($minutes % self::DAY, 60), $minutes % 60);
    }

    /** @return Interval|null */
    private static function interval(mixed $opens, mixed $closes): ?array
    {
        $from = self::minutes($opens);
        $to = self::minutes($closes);

        if ($from === null || $to === null) {
            return null;
        }

        return [$from, $to <= $from ? $to + self::DAY : $to];
    }

    private static function minutes(mixed $time): ?int
    {
        if (! is_string($time) || preg_match('/^([01]\d|2[0-3]):([0-5]\d)/', $time, $match) !== 1) {
            return null;
        }

        return (int) $match[1] * 60 + (int) $match[2];
    }

    /**
     * @param  list<Interval>  $intervals
     * @return list<Interval>
     */
    private static function sorted(array $intervals): array
    {
        usort($intervals, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_values(array_unique($intervals, SORT_REGULAR));
    }
}
