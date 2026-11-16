<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Fields;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Container\Container;
use Throwable;
use WebxUi\Settings\Settings;

/**
 * The «Date and time» field (FORM_STEPS §6): a day of a calendar and a time of a list, in the
 * site's time zone.
 *
 * Everything the field knows is here — its settings and their defaults, the times of a day, the
 * check of an answer and the snapshot of it — so that the panel's plan, the intake, the page and
 * MCP read one grid. It knows nothing of anybody else's answer: two visitors may choose the same
 * time, and a manager reading the submission is who settles it (§15).
 */
final class DateTimeField
{
    /** The steps of the list of times, in minutes. */
    public const array STEPS = [15, 20, 30, 60, 90, 120];

    public const int STEP = 30;

    public const string FROM = '09:00';

    public const string TO = '17:00';

    /** The problems of the plan (§6.2), as `inbox_form_save` and the panel name them. */
    public const string RANGE = 'datetime.range';

    public const string STEP_PROBLEM = 'datetime.step';

    public const string WEEKDAYS = 'datetime.weekdays';

    public const string LEAD = 'datetime.lead';

    public const string SKIP = 'datetime.skip';

    public const string ZONE_PROBLEM = 'datetime.zone';

    /** `timezone` of a field: the site's (the default, not written down) or the visitor's browser's. */
    public const string VISITOR = 'visitor';

    /** The longest `lead-days`: a year ahead. */
    public const int LEAD_MAX = 365;

    /** How the slots are drawn: a list under the calendar (default) or a panel headed by the day. */
    public const array SLOTS = ['list', 'panel'];

    /** The site's own setting of the zone (`module-settings`, the Contacts tab). */
    public const string SETTING = 'contacts.timezone';

    /**
     * The options of the field as they are kept: what is not of the right shape is not kept, and
     * the default stands in for it — {@see problems()} is what refuses a wrong one when saving.
     *
     * @param  array<mixed>  $input
     * @return array<string, mixed>
     */
    public static function clean(array $input): array
    {
        $step = $input['time-step'] ?? null;
        $weekdays = [];

        foreach (is_array($input['weekdays'] ?? null) ? $input['weekdays'] : [] as $day) {
            if (is_numeric($day)) {
                $weekdays[] = (int) $day;
            }
        }

        return [
            'time-step' => is_numeric($step) ? (int) $step : '',
            'time-from' => self::clock($input['time-from'] ?? null) ?? '',
            'time-to' => self::clock($input['time-to'] ?? null) ?? '',
            'weekdays' => $weekdays,
            'timezone-label' => self::words($input['timezone-label'] ?? null) ?? '',
            // The options of FORM_STEPS §6.7, each absent at its default — which is how a form saved
            // before them reads.
            'timezone' => ($input['timezone'] ?? null) === self::VISITOR ? self::VISITOR : '',
            'lead-days' => is_numeric($input['lead-days'] ?? null) && (int) $input['lead-days'] > 0 ? min(self::LEAD_MAX, (int) $input['lead-days']) : '',
            'time-skip' => self::skipOf($input['time-skip'] ?? null),
            'slots' => ($input['slots'] ?? null) === 'panel' ? 'panel' : '',
            'first-day' => filter_var($input['first-day'] ?? false, FILTER_VALIDATE_BOOL) ? true : '',
        ];
    }

    /**
     * What is wrong with the options sent for a field of this type, by the key it is about.
     *
     * @param  array<mixed>  $input
     * @return array<string, string> option => problem
     */
    public static function problems(array $input): array
    {
        $problems = [];
        $step = $input['time-step'] ?? null;

        if ($step !== null && $step !== '' && (! is_numeric($step) || ! in_array((int) $step, self::STEPS, true))) {
            $problems['time-step'] = self::STEP_PROBLEM;
        }

        foreach (['time-from', 'time-to'] as $key) {
            $value = $input[$key] ?? null;

            if ($value !== null && $value !== '' && self::clock($value) === null) {
                $problems[$key] = self::RANGE;
            }
        }

        if (! isset($problems['time-from']) && ! isset($problems['time-to']) && ! isset($problems['time-step'])) {
            $grid = self::grid(self::clean($input));

            // From before to, and room for one time at least: 16:45–17:00 with a step of an hour
            // offers nothing, and a field that offers nothing is a form nobody can send.
            if ($grid['from'] >= $grid['to'] || $grid['to'] < $grid['from'] + $grid['step']) {
                $problems['time-from'] = self::RANGE;
            }
        }

        $weekdays = $input['weekdays'] ?? null;

        if ($weekdays !== null && $weekdays !== '') {
            $days = is_array($weekdays) ? array_values($weekdays) : [$weekdays];
            $numbers = array_map(static fn (mixed $day): int => is_numeric($day) ? (int) $day : 0, $days);

            if (in_array(0, array_map(static fn (int $day): int => $day >= 1 && $day <= 7 ? $day : 0, $numbers), true)
                || count(array_unique($numbers)) !== count($numbers)) {
                $problems['weekdays'] = self::WEEKDAYS;
            }
        }

        $lead = $input['lead-days'] ?? null;

        if ($lead !== null && $lead !== '' && (! is_numeric($lead) || (int) $lead != $lead || (int) $lead < 0 || (int) $lead > self::LEAD_MAX)) {
            $problems['lead-days'] = self::LEAD;
        }

        $skip = $input['time-skip'] ?? null;

        if ($skip !== null && $skip !== '' && $skip !== []) {
            foreach (is_array($skip) ? $skip : [$skip] as $one) {
                if (self::clock($one) === null) {
                    $problems['time-skip'] = self::SKIP;

                    break;
                }
            }

            // Skipping every time of the day leaves a field nobody can answer.
            if (! isset($problems['time-skip']) && ! isset($problems['time-from']) && ! isset($problems['time-step']) && self::times(self::clean($input)) === []) {
                $problems['time-skip'] = self::SKIP;
            }
        }

        $zone = $input['timezone'] ?? null;

        if ($zone !== null && $zone !== '' && $zone !== self::VISITOR && $zone !== 'site') {
            $problems['timezone'] = self::ZONE_PROBLEM;
        }

        return $problems;
    }

    /** The words of a problem, for the panel and MCP. */
    public static function message(string $problem): string
    {
        return (string) trans('webx-inbox::errors.'.str_replace(['.', '_'], '-', $problem));
    }

    /**
     * The grid of a field with its defaults filled in: minutes from midnight, ISO days.
     *
     * @param  array<string, mixed>|null  $options
     * @return array{step: int, from: int, to: int, weekdays: list<int>, lead: int, skip: list<string>}
     */
    public static function grid(?array $options): array
    {
        $options ??= [];
        $step = $options['time-step'] ?? null;
        $weekdays = array_values(array_unique(array_filter(
            array_map(static fn (mixed $day): int => is_numeric($day) ? (int) $day : 0, is_array($options['weekdays'] ?? null) ? $options['weekdays'] : []),
            static fn (int $day): bool => $day >= 1 && $day <= 7,
        )));
        sort($weekdays);

        return [
            'step' => is_numeric($step) && in_array((int) $step, self::STEPS, true) ? (int) $step : self::STEP,
            'from' => self::minutes(self::clock($options['time-from'] ?? null) ?? self::FROM),
            'to' => self::minutes(self::clock($options['time-to'] ?? null) ?? self::TO),
            // None written down is every day.
            'weekdays' => $weekdays === [] ? [1, 2, 3, 4, 5, 6, 7] : $weekdays,
            // Days from today before the first that may be chosen: 1 — from tomorrow on.
            'lead' => is_numeric($options['lead-days'] ?? null) ? max(0, min(self::LEAD_MAX, (int) $options['lead-days'])) : 0,
            // Times of the grid not offered — a lunch break.
            'skip' => self::skipOf($options['time-skip'] ?? null),
        ];
    }

    /**
     * Whether the times are the visitor's own (§6.7): counted in the browser's zone, sent with it.
     *
     * @param  array<string, mixed>|null  $options
     */
    public static function visitor(?array $options): bool
    {
        return ($options['timezone'] ?? null) === self::VISITOR;
    }

    /**
     * The zone an answer is read in: the one the visitor's browser sent, for a field of the
     * visitor's zone that got one; the site's for every other — and for a page without JavaScript,
     * which sends none. A zone that does not exist is {@see refusal()}'s to refuse; here it is the site's.
     *
     * @param  array<string, mixed>|null  $options
     */
    public static function zoneOf(?array $options, mixed $value, ?string $site = null): string
    {
        $site ??= self::zone();
        $sent = is_array($value) && is_string($value['timezone'] ?? null) ? trim($value['timezone']) : '';

        return self::visitor($options) && $sent !== '' && self::known($sent) ? $sent : $site;
    }

    /**
     * The times of a day, `HH:MM`: from `time-from`, a step apart, the last a step before `time-to`.
     *
     * @param  array<string, mixed>|null  $options
     * @return list<string>
     */
    public static function times(?array $options): array
    {
        $grid = self::grid($options);
        $times = [];

        for ($at = $grid['from']; $at + $grid['step'] <= $grid['to']; $at += $grid['step']) {
            $time = sprintf('%02d:%02d', intdiv($at, 60), $at % 60);

            if (! in_array($time, $grid['skip'], true)) {
                $times[] = $time;
            }
        }

        return $times;
    }

    /**
     * The grid in one line, as the dry run of `inbox_form_save` and the panel's preview say it:
     * «09:00, 09:30 … 16:30, Mon–Fri».
     *
     * @param  array<string, mixed>|null  $options
     */
    public static function describe(?array $options, ?string $locale = null): string
    {
        $times = self::times($options);
        $line = count($times) > 3
            ? $times[0].', '.$times[1].' … '.$times[count($times) - 1]
            : implode(', ', $times);
        $grid = self::grid($options);

        return $grid['weekdays'] === [1, 2, 3, 4, 5, 6, 7] ? $line : $line.', '.self::days($grid['weekdays'], $locale);
    }

    /**
     * The days of the week as short words, a run of them with a dash: «Mon–Fri», «Mon, Wed, Fri».
     *
     * @param  list<int>  $weekdays
     */
    public static function days(array $weekdays, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        // A Monday: 2024-01-01.
        $name = static fn (int $day): string => CarbonImmutable::create(2024, 1, $day)->locale($locale)->isoFormat('ddd');
        $runs = [];

        foreach ($weekdays as $day) {
            $last = array_key_last($runs);

            if ($last !== null && $runs[$last][1] === $day - 1) {
                $runs[$last][1] = $day;
            } else {
                $runs[] = [$day, $day];
            }
        }

        return implode(', ', array_map(static fn (array $run): string => match (true) {
            $run[0] === $run[1] => $name($run[0]),
            $run[1] === $run[0] + 1 => $name($run[0]).', '.$name($run[1]),
            default => $name($run[0]).'–'.$name($run[1]),
        }, $runs));
    }

    /**
     * The zone of the site: the setting of its contacts, else the application's (§6.2). One place
     * of truth for the whole site; a field has no zone of its own.
     */
    public static function zone(): string
    {
        $container = Container::getInstance();

        if (class_exists(Settings::class) && $container->bound(Settings::class)) {
            try {
                $zone = $container->make(Settings::class)->get(self::SETTING);
            } catch (Throwable) {
                $zone = null;
            }

            if (is_string($zone) && self::known(trim($zone))) {
                return trim($zone);
            }
        }

        $zone = config('app.timezone');

        return is_string($zone) && self::known($zone) ? $zone : 'UTC';
    }

    /** «Europe/… (UTC+2)» — the zone as the page and the panel name it, with its offset now. */
    public static function zoneName(string $zone, ?CarbonImmutable $at = null): string
    {
        $offset = ($at ?? CarbonImmutable::now())->setTimezone($zone)->format('P');
        $offset = preg_replace('/^([+-])0?(\d+):00$/', '$1$2', $offset) ?? $offset;

        return $zone === 'UTC' ? 'UTC' : $zone.' (UTC'.($offset === '+0' ? '' : $offset).')';
    }

    /**
     * Why an answer is refused, or null: both parts, in their shape, a day of the week offered, a
     * time on the grid, not earlier than now in the site's zone (§6.3).
     *
     * @param  array<string, mixed>|null  $options
     * @return array{0: string, 1: array<string, string>}|null the key of the words and their params
     */
    public static function refusal(?array $options, mixed $value, string $zone, ?CarbonImmutable $now = null): ?array
    {
        $date = is_array($value) && is_string($value['date'] ?? null) ? trim($value['date']) : '';
        $time = is_array($value) && is_string($value['time'] ?? null) ? trim($value['time']) : '';

        if ($date === '') {
            return ['webx-inbox::form.datetime-date', []];
        }

        if ($time === '') {
            return ['webx-inbox::form.datetime-time', []];
        }

        // The visitor's zone (§6.7): one that exists, or the answer is refused — a page that sends
        // none (no JavaScript) is read in the site's.
        if (self::visitor($options) && is_array($value) && array_key_exists('timezone', $value)) {
            $sent = is_string($value['timezone']) ? trim($value['timezone']) : '';

            if ($sent !== '' && ! self::known($sent)) {
                return ['webx-inbox::form.datetime-zone', []];
            }

            $zone = $sent !== '' ? $sent : $zone;
        }

        $day = self::day($date, $zone);

        if ($day === null || self::clock($time) === null) {
            return ['webx-inbox::form.datetime-shape', []];
        }

        $grid = self::grid($options);

        if (! in_array($day->dayOfWeekIso, $grid['weekdays'], true)) {
            return ['webx-inbox::form.datetime-weekday', ['days' => self::days($grid['weekdays'])]];
        }

        $minutes = self::minutes($time);

        if ($minutes < $grid['from'] || $minutes + $grid['step'] > $grid['to'] || ($minutes - $grid['from']) % $grid['step'] !== 0
            || in_array(sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60), $grid['skip'], true)) {
            return ['webx-inbox::form.datetime-grid', []];
        }

        $now = ($now ?? CarbonImmutable::now())->setTimezone($zone);

        if ($day->setTime(intdiv($minutes, 60), $minutes % 60)->lessThan($now)) {
            return ['webx-inbox::form.datetime-past', []];
        }

        // `lead-days`: the first day that may be chosen is that many days after today, in the zone.
        if ($grid['lead'] > 0 && $day->lessThan($now->startOfDay()->addDays($grid['lead']))) {
            return ['webx-inbox::form.datetime-lead', ['date' => $now->startOfDay()->addDays($grid['lead'])->locale(app()->getLocale())->isoFormat('LL')]];
        }

        return null;
    }

    /** Neither part given: no answer, which is the same as an empty line of any other field. */
    public static function isEmpty(mixed $value): bool
    {
        if (! is_array($value)) {
            return $value === null || (is_string($value) && trim($value) === '');
        }

        foreach (['date', 'time'] as $part) {
            if (is_string($value[$part] ?? null) && trim($value[$part]) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * What is kept of a checked answer (§6.3): the line a person reads — «12.12.2026, 10:30
     * (Europe/…)», in the language of the submission — and beside it what the visitor saw and the
     * same moment in UTC, which is what a list sorts by.
     *
     * @param  array<string, mixed>  $value
     * @return array{0: string, 1: array{date: string, time: string, timezone: string, at: string}}
     */
    public static function snapshot(array $value, string $zone, ?string $locale = null): array
    {
        $date = trim((string) $value['date']);
        $time = (string) self::clock($value['time']);
        $moment = CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$time, $zone);

        if (! $moment instanceof CarbonImmutable) {
            // Only a checked answer gets here; this is the line for one that somehow was not.
            return [$date.' '.$time, ['date' => $date, 'time' => $time, 'timezone' => $zone, 'at' => '']];
        }

        $local = $moment->locale($locale ?? app()->getLocale());

        return [
            $local->isoFormat('L').', '.$local->isoFormat('LT').' ('.$zone.')',
            [
                'date' => $date,
                'time' => $time,
                'timezone' => $zone,
                'at' => $moment->utc()->format('Y-m-d\TH:i:s\Z'),
            ],
        ];
    }

    /** The day of an ISO date, or null for what is no date (2026-02-30 included). */
    private static function day(string $date, string $zone): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $zone);

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $date ? $day : null;
    }

    /**
     * `time-skip` as kept: `HH:MM` each, once, in order; a line «13:30, 14:00» is read as a list.
     *
     * @return list<string>
     */
    public static function skipOf(mixed $input): array
    {
        if (is_string($input)) {
            $input = preg_split('/[\s,;]+/', $input, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $times = [];

        foreach (is_array($input) ? $input : [] as $one) {
            $clock = self::clock($one);

            if ($clock !== null) {
                $times[$clock] = $clock;
            }
        }

        ksort($times);

        return array_values($times);
    }

    /** `HH:MM` of 00:00–24:00, or null; `9:30` is written `09:30`. */
    public static function clock(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $match) !== 1) {
            return null;
        }

        $hours = (int) $match[1];
        $minutes = (int) $match[2];

        if ($minutes > 59 || $hours > 24 || ($hours === 24 && $minutes !== 0)) {
            return null;
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    private static function minutes(string $clock): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $clock));

        return $hours * 60 + $minutes;
    }

    private static function known(string $zone): bool
    {
        return $zone === 'UTC' || in_array($zone, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * @return array<string, string>|string|null
     */
    private static function words(mixed $input): array|string|null
    {
        if (is_string($input)) {
            return trim($input) === '' ? null : trim($input);
        }

        if (! is_array($input)) {
            return null;
        }

        $words = [];

        foreach ($input as $locale => $line) {
            if (is_string($locale) && is_scalar($line) && trim((string) $line) !== '') {
                $words[$locale] = trim((string) $line);
            }
        }

        return $words === [] ? null : $words;
    }
}
