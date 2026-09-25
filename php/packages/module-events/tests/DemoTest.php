<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Events\Demo\EventsDemo;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Services\Models\Service;

/**
 * The demo events (§4.12): the rules they are there to show, the dates counted from the day of
 * seeding, and the links to the demo services.
 */
final class DemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->useLocales('en', 'ru');
    }

    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_asks_for_what_is_installed(): void
    {
        $this->assertSame(['media', 'services'], $this->app->make(EventsDemo::class)->requires());
    }

    #[Test]
    public function it_seeds_three_categories_and_six_events_each_showing_a_rule(): void
    {
        Carbon::setTestNow('2026-10-01 15:00:00');

        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame(3, EventCategory::query()->count());
        $this->assertSame(6, Event::query()->count());
        $this->assertSame(5, Event::query()->whereNotNull('published_at')->count());

        // Counted from the day of seeding: a week from today, with its hours.
        $class = $this->named('spring-cooking-class');
        $this->assertSame('2026-10-08T10:00:00+00:00', $class->starts_at?->toAtomString());
        $this->assertSame('2026-10-08T12:30:00+00:00', $class->ends_at?->toAtomString());
        $this->assertSame(480.0, $class->price_amount);
        $this->assertSame('Your own stove', $class->highlights('en')[0]['title'] ?? null);

        // Whole days, and in two categories — the first the main one.
        $intensive = $this->named('fermentation-intensive');
        $this->assertTrue($intensive->all_day);
        $this->assertSame('2026-10-21T00:00:00+00:00', $intensive->starts_at?->toAtomString());
        $this->assertSame('2026-10-23T23:59:59+00:00', $intensive->ends_at?->toAtomString());
        $this->assertSame(['cooking-classes', 'private-events'], $intensive->categories->map(static fn (EventCategory $category): string => (string) $category->getTranslation('slug', 'en'))->all());

        $webinar = $this->named('meal-planning-webinar');
        $this->assertSame(Event::ONLINE, $webinar->attendance);
        $this->assertSame(0.0, $webinar->price_amount);

        $club = $this->named('saturday-breakfast-club');
        $this->assertNull($club->starts_at);
        $this->assertSame('Каждую субботу, 9:00', $club->getTranslation('date_note', 'ru'));

        $autumn = $this->named('autumn-breakfast-meetup');
        $this->assertTrue($autumn->isPast());
        $this->assertCount(2, $autumn->pictures());

        $this->assertSame('draft', $this->named('chefs-table')->status());

        // The events to come, in the order the site shows them: no date first.
        $this->assertSame(
            ['saturday-breakfast-club', 'spring-cooking-class', 'meal-planning-webinar', 'fermentation-intensive', 'chefs-table'],
            Event::query()->scopes(['upcoming'])->get()->map(static fn (Event $event): string => (string) $event->getTranslation('slug', 'en'))->all(),
        );
    }

    #[Test]
    public function two_events_are_linked_to_the_demo_services(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $slugs = fn (Event $event): array => Service::query()
            ->whereKey($event->relatedIds(Event::SERVICES))
            ->get()
            ->map(static fn (Service $service): string => (string) $service->getTranslation('slug', 'en'))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['content-editing'], $slugs($this->named('spring-cooking-class')));
        $this->assertSame(['content-editing', 'seo-audit'], $slugs($this->named('autumn-breakfast-meetup')));
    }

    #[Test]
    public function the_pages_answer_and_the_past_one_is_a_report(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();

        $index = $this->get('/events')->assertOk();
        $index->assertSee('Spring cooking class');
        $index->assertSee('Every Saturday, 9:00');
        $index->assertDontSee('Autumn breakfast meetup');
        $index->assertDontSee('Chef&#039;s table', false);

        $markup = $this->jsonLd($this->get('/events/spring-cooking-class')->assertOk()->getContent() ?: '', 'Event');
        $this->assertSame('Spring cooking class', $markup['name']);
        $this->assertSame('480', (string) ($markup['offers']['price'] ?? '480'));

        $this->get('/events/autumn-breakfast-meetup')->assertOk()->assertSee('This event is over.')->assertDontSee('wx-event__book"', false);
        $this->get('/events/spring-cooking-class.ics')->assertOk();
    }

    #[Test]
    public function removing_takes_everything_back_out(): void
    {
        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Event::withTrashed()->count());
        $this->assertSame(0, EventCategory::withTrashed()->count());
    }

    private function named(string $slug): Event
    {
        return Event::query()->where('slug->en', $slug)->firstOrFail();
    }
}
