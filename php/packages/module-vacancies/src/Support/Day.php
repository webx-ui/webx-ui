<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * A calendar day as `Y-m-d` — what `valid_through` and `posted_at` are (§3).
 *
 * A day is not a moment, and it is read as written: `2026-11-30` from the picker is that day, in
 * whatever timezone the reader is. Only "today" needs a zone, and it is the application's — a
 * vacancy is open the whole of its last day where the site is, not where the server's clock is.
 */
final class Day
{
    /** Today, in the application's timezone. */
    public static function today(): string
    {
        return Carbon::now((string) config('app.timezone', 'UTC'))->toDateString();
    }

    /**
     * The day of a value, or null for nothing and for what is not a day. A string's own date is
     * taken as written, whatever time or offset follows it; a moment gives the day on its own
     * clock.
     */
    public static function from(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $match) === 1) {
            return checkdate((int) $match[2], (int) $match[3], (int) $match[1]) ? $match[1].'-'.$match[2].'-'.$match[3] : null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /** Whether a value is a day, or nothing — what a check asks before it compares two. */
    public static function valid(mixed $value): bool
    {
        return $value === null || $value === '' || self::from($value) !== null;
    }
}
