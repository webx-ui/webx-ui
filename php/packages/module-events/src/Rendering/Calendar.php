<?php

declare(strict_types=1);

namespace WebxUi\Events\Rendering;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use WebxUi\Events\Models\Event;

/**
 * An event as a calendar file (§4.3): one `VEVENT` of RFC 5545, written by hand — the format is
 * twenty lines, and a library for it would be the largest part of the module.
 *
 * Two rules of the format are easy to get wrong and are what the tests are about. A line is at
 * most 75 octets, and a longer one goes on in a line that starts with a space — octets, not
 * characters, and never in the middle of a character. And in text `\`, `;` and `,` are escaped and
 * a line break is written `\n`. An event of days has dates without a time, and its end is the day
 * *after* its last one: the format's end is exclusive.
 */
final class Calendar
{
    private const LIMIT = 75;

    public function of(Event $event, string $locale, ?Carbon $now = null): string
    {
        $url = $event->url($locale);
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: 'localhost');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//WebX UI//module-events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-'.$event->getKey().'@'.$host,
            'DTSTAMP:'.self::utc($now ?? Carbon::now()),
            ...$this->dates($event),
            'SUMMARY:'.self::escape($event->text('title', $locale)),
        ];

        $lead = $event->text('lead', $locale);

        if ($lead !== '') {
            $lines[] = 'DESCRIPTION:'.self::escape($lead);
        }

        $where = $this->location($event, $locale);

        if ($where !== '') {
            $lines[] = 'LOCATION:'.self::escape($where);
        }

        $lines[] = 'URL:'.$url;
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /** The name the file is saved under: the last segment of the address. */
    public function filename(Event $event, string $locale): string
    {
        $slug = $event->text('slug', $locale);

        return ($slug !== '' ? $slug : 'event-'.$event->getKey()).'.ics';
    }

    /**
     * Text as the format wants it: backslash, semicolon and comma escaped, a line break as `\n`.
     */
    public static function escape(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
    }

    /**
     * One content line cut into lines of at most 75 octets, each after the first starting with a
     * space — the space counts towards its 75. Never inside a multibyte character.
     */
    public static function fold(string $line): string
    {
        if (strlen($line) <= self::LIMIT) {
            return $line;
        }

        $parts = [];
        $current = '';
        $limit = self::LIMIT;

        foreach (mb_str_split($line) as $character) {
            if (strlen($current) + strlen($character) > $limit) {
                $parts[] = $current;
                $current = '';
                // Every following line starts with the space that says "continued".
                $limit = self::LIMIT - 1;
            }

            $current .= $character;
        }

        $parts[] = $current;

        return implode("\r\n ", $parts);
    }

    /**
     * @return list<string>
     */
    private function dates(Event $event): array
    {
        $start = $event->starts_at;

        if ($start === null) {
            return [];
        }

        if ($event->all_day) {
            $last = ($event->ends_at ?? $start)->copy();

            return [
                'DTSTART;VALUE=DATE:'.$start->format('Ymd'),
                'DTEND;VALUE=DATE:'.$last->startOfDay()->addDay()->format('Ymd'),
            ];
        }

        $dates = ['DTSTART:'.self::utc($start)];

        if ($event->ends_at !== null && $event->ends_at->greaterThan($start)) {
            $dates[] = 'DTEND:'.self::utc($event->ends_at);
        }

        return $dates;
    }

    private function location(Event $event, string $locale): string
    {
        if (! $event->hasPlace()) {
            return '';
        }

        return implode(', ', array_filter([$event->text('venue', $locale), $event->text('address', $locale)], static fn (string $part): bool => $part !== ''));
    }

    private static function utc(DateTimeInterface $moment): string
    {
        return Carbon::instance($moment)->utc()->format('Ymd\THis\Z');
    }
}
