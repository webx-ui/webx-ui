<?php

declare(strict_types=1);

namespace WebxUi\Events\Rendering;

use Carbon\CarbonInterface;
use WebxUi\Events\Models\Event;

/**
 * When an event is, in words (§4.6) — one helper, so the page, the cards, the panel's list and an
 * agent's catalogue all say it alike.
 *
 *     a note written                 the note, and nothing else
 *     no date                        nothing
 *     one day, a time and an end     12 October 2026, 10:00–12:30
 *     one day, a time                12 October 2026, 10:00
 *     one day of an event of days    12 October 2026
 *     several days                   12–14 October 2026 · 30 September – 2 October 2026
 *
 * The note overrides what is printed, not what is known: the order, "past", the calendar file and
 * the markup still go by the date. The months are the language's own, in the case a day takes
 * ("12 октября", not "12 октябрь") — `isoFormat` knows that, `format` does not.
 */
final class When
{
    public static function of(Event $event, string $locale): string
    {
        $note = $event->text('date_note', $locale);

        if ($note !== '') {
            return $note;
        }

        return self::between($event->starts_at, $event->ends_at, $event->all_day, $locale);
    }

    public static function between(?CarbonInterface $start, ?CarbonInterface $end, bool $allDay, string $locale): string
    {
        if ($start === null) {
            return '';
        }

        $start = $start->copy()->locale($locale);
        $end = $end?->copy()->locale($locale);

        if ($end !== null && ! $start->isSameDay($end) && $end->greaterThan($start)) {
            return self::days($start, $end);
        }

        $day = $start->isoFormat('D MMMM YYYY');

        if ($allDay) {
            return $day;
        }

        $time = $start->format('H:i');

        if ($end !== null && $end->greaterThan($start)) {
            $time .= '–'.$end->format('H:i');
        }

        return $day.', '.$time;
    }

    /** 12–14 October 2026, 30 September – 2 October 2026, 30 December 2026 – 2 January 2027. */
    private static function days(CarbonInterface $start, CarbonInterface $end): string
    {
        $last = $end->isoFormat('D MMMM YYYY');

        if ($start->year !== $end->year) {
            return $start->isoFormat('D MMMM YYYY').' – '.$last;
        }

        if ($start->month !== $end->month) {
            return $start->isoFormat('D MMMM').' – '.$last;
        }

        return $start->isoFormat('D').'–'.$last;
    }
}
