<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Events\Models\Event;

/**
 * schema.org `Event` (§4.7): only with a date; days without a time for an event of days; the
 * place by how people attend; a price only as a number with the site's currency, and zero as free.
 */
final class MarkupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00'));
    }

    #[Test]
    public function an_event_without_a_date_has_no_markup(): void
    {
        $this->event('saturdays', attributes: ['date_note' => 'Every Saturday']);

        $page = (string) $this->get('/events/saturdays')->assertOk()->getContent();

        $this->assertNotContains('Event', array_column($this->jsonLds($page), '@type'));
    }

    #[Test]
    public function a_dated_event_in_person_with_a_price(): void
    {
        $this->app['config']->set('webx-events.currency', 'hkd');

        $event = $this->event('spring-class', '2026-10-12 10:00:00', attributes: [
            'ends_at' => '2026-10-12 12:30:00',
            'lead' => 'Three hours of dumplings.',
            'venue' => 'Studio Kitchen',
            'address' => '1 Queen’s Road, Hong Kong',
            'price' => 'HK$480 per person',
            'price_amount' => 480,
            'booking_url' => 'https://book.example.test/spring',
        ]);

        $markup = $this->jsonLd((string) $this->get('/events/spring-class')->getContent(), 'Event');

        $this->assertSame('Spring class', $markup['name']);
        $this->assertSame('Three hours of dumplings.', $markup['description']);
        $this->assertSame($event->url(), $markup['url']);
        $this->assertSame('2026-10-12T10:00:00+00:00', $markup['startDate']);
        $this->assertSame('2026-10-12T12:30:00+00:00', $markup['endDate']);
        $this->assertSame('https://schema.org/EventScheduled', $markup['eventStatus']);
        $this->assertSame('https://schema.org/OfflineEventAttendanceMode', $markup['eventAttendanceMode']);
        $this->assertSame(['@type' => 'Place', 'name' => 'Studio Kitchen', 'address' => '1 Queen’s Road, Hong Kong'], $markup['location']);
        $this->assertSame(['@type' => 'Offer', 'url' => 'https://book.example.test/spring', 'price' => '480', 'priceCurrency' => 'HKD'], $markup['offers']);
        $this->assertArrayNotHasKey('isAccessibleForFree', $markup);
    }

    #[Test]
    public function without_a_currency_there_is_no_price_and_zero_is_free(): void
    {
        $this->event('paid', '2026-10-12 10:00:00', attributes: ['price_amount' => 480]);
        $this->event('free', '2026-10-13 10:00:00', attributes: ['price_amount' => 0]);

        $paid = $this->jsonLd((string) $this->get('/events/paid')->getContent(), 'Event');
        $this->assertArrayNotHasKey('offers', $paid);

        $free = $this->jsonLd((string) $this->get('/events/free')->getContent(), 'Event');
        $this->assertTrue($free['isAccessibleForFree']);
    }

    #[Test]
    public function an_event_of_days_has_dates_without_a_time(): void
    {
        $this->event('festival', '2026-10-12', attributes: ['ends_at' => '2026-10-14', 'all_day' => true]);

        $markup = $this->jsonLd((string) $this->get('/events/festival')->getContent(), 'Event');

        $this->assertSame('2026-10-12', $markup['startDate']);
        $this->assertSame('2026-10-14', $markup['endDate']);
    }

    #[Test]
    public function online_is_a_virtual_location_and_mixed_is_both(): void
    {
        $this->event('webinar', '2026-10-12 10:00:00', attributes: [
            'attendance' => Event::ONLINE,
            'venue' => 'Never printed',
            'booking_url' => 'https://book.example.test/webinar',
        ]);
        $this->event('hybrid', '2026-10-13 10:00:00', attributes: ['attendance' => Event::MIXED, 'venue' => 'Studio']);

        $online = $this->jsonLd((string) $this->get('/events/webinar')->getContent(), 'Event');
        $this->assertSame('https://schema.org/OnlineEventAttendanceMode', $online['eventAttendanceMode']);
        $this->assertSame(['@type' => 'VirtualLocation', 'url' => 'https://book.example.test/webinar'], $online['location']);

        $mixed = $this->jsonLd((string) $this->get('/events/hybrid')->getContent(), 'Event');
        $this->assertSame('https://schema.org/MixedEventAttendanceMode', $mixed['eventAttendanceMode']);
        $this->assertSame(['Place', 'VirtualLocation'], array_column($mixed['location'], '@type'));
        $this->assertStringEndsWith('/events/hybrid', $mixed['location'][1]['url']);
    }
}
