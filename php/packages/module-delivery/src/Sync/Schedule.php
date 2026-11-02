<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;
use WebxUi\Geography\Names;

/**
 * The opening hours of a point (§7.4), as `delivery_points.schedule` holds them:
 *
 *     {"timezone": "UTC", "week": {"mon": [["09:00", "18:00"]], "sat": [], "sun": null},
 *      "exceptions": [{"date": "2026-12-31", "hours": [["09:00", "15:00"]]}],
 *      "text": {"en": "Closed on public holidays"}}
 *
 * A day's list of intervals; `[]` — closed; `null` or no key — the carrier did not say. Around
 * the clock is `[["00:00", "24:00"]]`. A carrier that gives the hours only as a line puts it in
 * `text` and leaves the week empty.
 */
final readonly class Schedule
{
    public const array DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * @param  array<string, list<array{0: string, 1: string}>|null>  $week
     * @param  list<array{date: string, hours: list<array{0: string, 1: string}>}>  $exceptions
     * @param  array<string, string>  $text
     */
    public function __construct(
        public array $week = [],
        public ?string $timezone = null,
        public array $exceptions = [],
        public array $text = [],
    ) {}

    /** @param  array<string, mixed>|null  $data */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        $week = [];

        foreach (self::DAYS as $day) {
            if (array_key_exists($day, (array) ($data['week'] ?? []))) {
                $hours = $data['week'][$day];
                $week[$day] = is_array($hours) ? self::hours($hours) : null;
            }
        }

        $exceptions = [];

        foreach ((array) ($data['exceptions'] ?? []) as $exception) {
            if (is_array($exception) && is_string($exception['date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $exception['date']) === 1) {
                $exceptions[] = ['date' => $exception['date'], 'hours' => self::hours((array) ($exception['hours'] ?? []))];
            }
        }

        return new self(
            $week,
            is_string($data['timezone'] ?? null) ? $data['timezone'] : null,
            $exceptions,
            Names::clean(is_array($data['text'] ?? null) ? $data['text'] : null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'timezone' => $this->timezone,
            'week' => $this->week,
            'exceptions' => $this->exceptions,
            'text' => $this->text,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * Whether the point is open at that moment, in its own time zone; null — the carrier did not
     * say for that day.
     */
    public function openAt(DateTimeInterface $moment, string $siteTimezone = 'UTC'): ?bool
    {
        $hours = $this->hoursOn($local = $this->local($moment, $siteTimezone));

        if ($hours === null) {
            return null;
        }

        $time = $local->format('H:i');

        foreach ($hours as [$from, $to]) {
            if ($time >= $from && $time < $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * Today in a few words for a list of points: «until 18:00», «closed», «open 24 hours», or the
     * carrier's own line; null when nothing is known.
     */
    public function todayLabel(?string $locale = null, ?DateTimeInterface $now = null, string $siteTimezone = 'UTC'): ?string
    {
        $local = $this->local($now ?? new DateTimeImmutable, $siteTimezone);
        $hours = $this->hoursOn($local);
        $words = static fn (string $key, array $replace = []): string => (string) __('webx-delivery::delivery.schedule.'.$key, $replace, $locale);

        if ($hours === null) {
            return Names::pick($this->text, $locale);
        }

        if ($hours === []) {
            return $words('closed');
        }

        if ($hours === [['00:00', '24:00']]) {
            return $words('around_the_clock');
        }

        $time = $local->format('H:i');

        foreach ($hours as [$from, $to]) {
            if ($time < $from) {
                return $words('from', ['time' => $from]);
            }

            if ($time < $to) {
                return $words('until', ['time' => $to]);
            }
        }

        return $words('closed');
    }

    /** @return list<array{0: string, 1: string}>|null */
    private function hoursOn(DateTimeImmutable $day): ?array
    {
        foreach ($this->exceptions as $exception) {
            if ($exception['date'] === $day->format('Y-m-d')) {
                return $exception['hours'];
            }
        }

        return $this->week[strtolower($day->format('D'))] ?? null;
    }

    private function local(DateTimeInterface $moment, string $siteTimezone): DateTimeImmutable
    {
        try {
            $zone = new DateTimeZone($this->timezone ?? $siteTimezone);
        } catch (Throwable) {
            $zone = new DateTimeZone('UTC');
        }

        return DateTimeImmutable::createFromInterface($moment)->setTimezone($zone);
    }

    /**
     * Intervals «HH:MM»–«HH:MM», the end after the start; anything else is dropped.
     *
     * @param  array<array-key, mixed>  $hours
     * @return list<array{0: string, 1: string}>
     */
    private static function hours(array $hours): array
    {
        $clean = [];

        foreach ($hours as $interval) {
            if (! is_array($interval) || count($interval) !== 2) {
                continue;
            }

            [$from, $to] = array_values($interval);

            if (is_string($from) && is_string($to) && preg_match('/^([01]\d|2[0-4]):[0-5]\d$/', $from) === 1
                && preg_match('/^([01]\d|2[0-4]):[0-5]\d$/', $to) === 1 && $from < $to) {
                $clean[] = [$from, $to];
            }
        }

        return $clean;
    }
}
