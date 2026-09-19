<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use Illuminate\Support\Carbon;

/**
 * The moment a date from the panel names, ready to be written to a column.
 *
 * The panel sends the day and the hour with the reader's offset on them —
 * `2026-09-25T11:06:00+03:00` — because a wall clock with no zone is read by the server in the
 * application's timezone and by the browser in the reader's, and one article ends up listed at
 * one time and edited at another.
 *
 * The conversion at the end is the part that is easy to lose. Eloquent writes a `Carbon` out
 * with its own timezone still on it: a moment parsed as `+03:00` is stored as the literal
 * `2026-09-25 11:06:00` and read back as that hour in the application's zone, which moves the
 * article by the offset every time it is saved. Nothing warns about it — the date is simply
 * three hours out, in a direction that depends on who saved it.
 */
final class Instant
{
    public static function from(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $timezone = config('app.timezone');

        return Carbon::parse($value)->setTimezone(is_string($timezone) && $timezone !== '' ? $timezone : 'UTC');
    }
}
