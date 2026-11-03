<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel\Fields;

use Closure;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Delivery\Sync\Schedule;

/**
 * `wx-delivery-schedule`: the opening hours of a point (§7.4) — the week and the exceptions, as
 * `delivery_points.schedule` keeps them. An interval is two `HH:MM` with the start first; `24:00`
 * ends a day that runs to midnight.
 */
final class ScheduleField implements FieldType
{
    private const string TIME = '/^(?:[01]\d|2[0-3]):[0-5]\d$|^24:00$/';

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'array', static function (string $attribute, mixed $value, Closure $fail): void {
            $value = (array) $value;
            $intervals = [];

            foreach ((array) ($value['week'] ?? []) as $day => $hours) {
                if (! in_array($day, Schedule::DAYS, true) || ($hours !== null && ! is_array($hours))) {
                    $fail(__('webx-delivery::panel.errors.schedule'));

                    return;
                }

                array_push($intervals, ...($hours ?? []));
            }

            foreach ((array) ($value['exceptions'] ?? []) as $exception) {
                if (! is_array($exception) || ! is_string($exception['date'] ?? null) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $exception['date']) !== 1) {
                    $fail(__('webx-delivery::panel.errors.schedule'));

                    return;
                }

                array_push($intervals, ...(array) ($exception['hours'] ?? []));
            }

            foreach ($intervals as $interval) {
                $from = is_array($interval) ? ($interval[0] ?? null) : null;
                $to = is_array($interval) ? ($interval[1] ?? null) : null;

                if (! is_string($from) || ! is_string($to) || preg_match(self::TIME, $from) !== 1 || preg_match(self::TIME, $to) !== 1 || $from >= $to) {
                    $fail(__('webx-delivery::panel.errors.schedule'));

                    return;
                }
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    public function store(mixed $value, array $node): ?array
    {
        $schedule = Schedule::fromArray(is_array($value) ? $value : null)?->toArray();

        return $schedule === null || $schedule === [] ? null : $schedule;
    }

    /** @param  array<string, mixed>  $node */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
