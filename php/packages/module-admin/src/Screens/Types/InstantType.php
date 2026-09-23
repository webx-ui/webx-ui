<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Illuminate\Support\Carbon;
use Throwable;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-date-time-picker`: a moment, kept as an ISO 8601 string with its offset —
 * `2026-09-25T08:06:00+00:00`.
 *
 * The registry binds the picker to write the reader's offset on the value, because a wall clock
 * with no zone is read by the server in the application's timezone and by the browser in the
 * reader's, and one value ends up shown at two different hours. What is kept is the same moment
 * moved into the application's timezone, so every stored value reads alike and a template can
 * compare two of them as strings. A value that arrives without an offset is taken to be in the
 * application's timezone — the one reading the server can make of it.
 */
final class InstantType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'date'];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $timezone = config('app.timezone');
        $timezone = is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';

        try {
            return Carbon::parse($value, $timezone)->setTimezone($timezone)->toAtomString();
        } catch (Throwable) {
            // A block's values are kept without rules, and a moment nobody can read is no moment.
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
