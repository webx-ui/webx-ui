<?php

declare(strict_types=1);

namespace WebxUi\Events\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * The moment a value names, moved into the application's timezone — ready to be written to a
 * column (decision 15).
 *
 * The panel sends a start with the reader's offset on it — `2026-10-12T10:00:00+03:00` — and an
 * agent may send one without any. The conversion at the end is the part that is easy to lose:
 * Eloquent writes a `Carbon` out with its own timezone still on it, so a moment parsed as
 * `+03:00` is stored as the literal `10:00:00` and read back as ten o'clock in the application's
 * zone — three hours out, in a direction that depends on who saved it (CLAUDE.md §4). A value
 * without an offset is read in the application's timezone, the one reading the server can make.
 */
final class Moment
{
    /**
     * @throws InvalidArgumentException A string nobody can read as a date.
     */
    public static function from(mixed $value): ?Carbon
    {
        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return Carbon::instance($value)->setTimezone(self::zone());
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value), self::zone())->setTimezone(self::zone());
        } catch (Throwable $exception) {
            throw new InvalidArgumentException("Not a date: {$value}", 0, $exception);
        }
    }

    /** Like {@see from()}, but a value nobody can read is no moment rather than an error. */
    public static function tryFrom(mixed $value): ?Carbon
    {
        try {
            return self::from($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The day a value names, as the midnight that starts it in the application's timezone.
     *
     * For an event of days the moment is not the point, the calendar date is — and it is the date
     * in the offset the value came with. A reader at +03:00 who picks 12 October sends
     * `2026-10-12T00:00:00+03:00`, which is 11 October in UTC: moved into the application's zone
     * first and cut to a day second, the event would start a day early.
     */
    public static function day(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            $date = $value->format('Y-m-d');
        } elseif (is_string($value) && trim($value) !== '') {
            try {
                $date = Carbon::parse(trim($value), self::zone())->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        } else {
            return null;
        }

        return Carbon::parse($date, self::zone())->startOfDay();
    }

    public static function zone(): string
    {
        $zone = config('app.timezone');

        return is_string($zone) && $zone !== '' ? $zone : 'UTC';
    }
}
