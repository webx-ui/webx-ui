<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use Illuminate\Support\Carbon;
use Throwable;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-date-range-picker`: two dates, `[start, end]`, each the string the picker produced —
 * `YYYY-MM-DD` unless the node sets its own `valueFormat`. The start may not come after the end.
 */
final class DateRangeType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'array', 'list', 'size:2', static function (string $attribute, mixed $value, Closure $fail): void {
            $dates = [];

            foreach (is_array($value) ? $value : [] as $end) {
                $date = self::parse($end);

                if ($date === null) {
                    $fail('validation.date')->translate();

                    return;
                }

                $dates[] = $date;
            }

            if (count($dates) === 2 && $dates[0]->greaterThan($dates[1])) {
                $fail('validation.in')->translate();
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if (! is_array($value) || count($value) !== 2) {
            return null;
        }

        [$start, $end] = array_values($value);

        return is_string($start) && $start !== '' && is_string($end) && $end !== '' ? [$start, $end] : null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }

    private static function parse(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
