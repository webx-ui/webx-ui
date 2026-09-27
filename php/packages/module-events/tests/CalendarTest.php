<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Events\Rendering\Calendar;

/**
 * The `.ics` file (§4.3): the two rules of RFC 5545 that are easy to get wrong — folding at 75
 * octets and escaping text — and the day after the last one as the end of an event of days.
 */
final class CalendarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00'));
    }

    #[Test]
    public function a_dated_event_is_one_vevent_in_utc(): void
    {
        $this->event('spring-class', '2026-10-12 10:00:00', attributes: [
            'ends_at' => '2026-10-12 12:30:00',
            'lead' => 'Dumplings, noodles; and tea',
            'venue' => 'Studio Kitchen',
            'address' => '1 Queen Road, Hong Kong',
        ]);

        $response = $this->get('/events/spring-class.ics')->assertOk();

        $this->assertStringStartsWith('text/calendar', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('filename="spring-class.ics"', (string) $response->headers->get('Content-Disposition'));

        $body = (string) $response->getContent();

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $body);
        $this->assertStringContainsString("\r\nUID:event-1@localhost\r\n", $body);
        $this->assertStringContainsString("\r\nDTSTART:20261012T100000Z\r\n", $body);
        $this->assertStringContainsString("\r\nDTEND:20261012T123000Z\r\n", $body);
        $this->assertStringContainsString("\r\nSUMMARY:Spring class\r\n", $body);
        $this->assertStringContainsString("\r\nDESCRIPTION:Dumplings\\, noodles\\; and tea\r\n", $body);
        $this->assertStringContainsString("\r\nLOCATION:Studio Kitchen\\, 1 Queen Road\\, Hong Kong\r\n", $body);
        $this->assertStringContainsString("\r\nURL:http://localhost/events/spring-class\r\n", $body);
        $this->assertStringEndsWith("END:VEVENT\r\nEND:VCALENDAR\r\n", $body);
    }

    #[Test]
    public function an_event_of_days_ends_the_day_after_its_last(): void
    {
        $this->event('festival', '2026-10-12', attributes: ['ends_at' => '2026-10-14', 'all_day' => true]);

        $body = (string) $this->get('/events/festival.ics')->assertOk()->getContent();

        $this->assertStringContainsString("\r\nDTSTART;VALUE=DATE:20261012\r\n", $body);
        $this->assertStringContainsString("\r\nDTEND;VALUE=DATE:20261015\r\n", $body);
    }

    #[Test]
    public function text_is_escaped_and_long_lines_are_folded_at_75_octets(): void
    {
        $this->assertSame('a\\\\b\\;c\\,d\\ne', Calendar::escape("a\\b;c,d\r\ne"));

        // Cyrillic letters are two octets each: the fold must count bytes, and never cut one.
        $line = 'SUMMARY:'.str_repeat('Мастер-класс по пельменям ', 6);
        $folded = Calendar::fold($line);
        $parts = explode("\r\n", $folded);

        $this->assertGreaterThan(1, count($parts));

        foreach ($parts as $index => $part) {
            $this->assertLessThanOrEqual(75, strlen($part));
            $this->assertTrue(mb_check_encoding($part, 'UTF-8'));

            if ($index > 0) {
                $this->assertStringStartsWith(' ', $part);
            }
        }

        $this->assertSame($line, implode('', array_map(
            static fn (string $part, int $index): string => $index === 0 ? $part : substr($part, 1),
            $parts,
            array_keys($parts),
        )));
    }

    #[Test]
    public function an_event_without_a_date_a_draft_and_nothing_at_all_are_404(): void
    {
        $this->event('saturdays', attributes: ['date_note' => 'Every Saturday']);
        $this->event('draft', '2026-10-12 10:00:00', published: false);

        $this->get('/events/saturdays.ics')->assertNotFound();
        $this->get('/events/draft.ics')->assertNotFound();
        $this->get('/events/nothing.ics')->assertNotFound();
    }

    #[Test]
    public function the_page_links_to_its_file_until_the_event_is_over(): void
    {
        $this->event('spring-class', '2026-10-12 10:00:00', attributes: ['booking_url' => 'https://book.example.test']);

        $this->get('/events/spring-class')
            ->assertOk()
            ->assertSee('href="http://localhost/events/spring-class.ics"', false)
            ->assertSee('href="https://book.example.test"', false);

        Carbon::setTestNow(Carbon::parse('2026-11-01 09:00:00'));

        $this->get('/events/spring-class')
            ->assertOk()
            ->assertSee('This event is over.')
            ->assertDontSee('.ics"', false)
            ->assertDontSee('wx-event__book', false)
            ->assertDontSee('wx-event__booking', false);
    }
}
