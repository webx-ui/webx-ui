<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cache\CacheGroups;
use WebxUi\Events\Models\Event;
use WebxUi\Media\Models\MediaFile;

/**
 * The query budget of every public page of the events, and the freshness of what they cache
 * (WEBX_UI_PERFORMANCE_AUDIT.md §7).
 *
 * Every budget is a ceiling the page fits under today with thirty events, not a target, and the
 * point of each is that it does not grow with the rows: a page that asks the registry or the
 * library once per card is caught by `assertNoRepeatedQueries()` on the warm pass whatever the
 * count. A change that needs more says why here before raising the number.
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

    private const EVENTS = 30;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The caches on, the way a site has them: the budget of a warm page is the point.
        $app['config']->set('webx-localization.cache.enabled', true);
        $app['config']->set('webx-seo.cache.enabled', true);
        // With a currency the markup carries an offer, as a site that sells tickets has it.
        $app['config']->set('webx-events.currency', 'EUR');
    }

    #[Test]
    public function the_index_fits_its_budget_cold_and_warm(): void
    {
        $this->seedEvents();

        $this->cold();
        $this->assertQueriesWithin(12, fn () => $this->get('/events')->assertOk());

        $this->assertQueriesWithin(7, fn () => $this->get('/events')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/events')->assertOk());
    }

    #[Test]
    public function a_category_fits_its_budget_cold_and_warm(): void
    {
        $this->seedEvents();

        $this->cold();
        $this->assertQueriesWithin(14, fn () => $this->get('/events/cooking-classes')->assertOk());

        $this->assertQueriesWithin(8, fn () => $this->get('/events/cooking-classes')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/events/cooking-classes')->assertOk());
    }

    #[Test]
    public function an_event_and_its_calendar_file_fit_their_budgets_cold_and_warm(): void
    {
        $this->seedEvents();

        $this->cold();
        $this->assertQueriesWithin(16, fn () => $this->get('/events/event-2')->assertOk());

        $this->assertQueriesWithin(11, fn () => $this->get('/events/event-2')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/events/event-2')->assertOk());

        $this->cold();
        $this->assertQueriesWithin(7, fn () => $this->get('/events/event-2.ics')->assertOk());

        $this->assertQueriesWithin(4, fn () => $this->get('/events/event-2.ics')->assertOk());
    }

    #[Test]
    public function the_sitemap_fits_its_budget(): void
    {
        $this->seedEvents();

        // The build: the events and the categories, chunked, with their cards and the covers
        // behind `og:image` in bulk; the date is a column.
        $this->cold();
        $this->assertQueriesWithin(18, fn () => $this->get('/sitemap.xml')->assertOk());
        // Served from the cache: nothing but the request's own infrastructure.
        $this->assertQueriesWithin(2, fn () => $this->get('/sitemap-event.xml')->assertOk());
    }

    /**
     * The registry and the shared data of a page are cached on versioned keys; a save through
     * the panel moves their groups, and the next request — in the same process, the way a worker
     * serves it — reads the new rows. The first event is the one on the first page.
     */
    #[Test]
    public function a_page_shows_a_change_made_through_the_panel_at_once(): void
    {
        $this->seedEvents();
        $editor = $this->editor();

        $this->get('/events')->assertOk()->assertSee('Event 1');
        $this->get('/events/event-1')->assertOk()->assertSee('Event 1');

        $event = Event::query()->where('slug->en', 'event-1')->firstOrFail();
        $this->actingAs($editor, 'cms')
            ->putJson($this->api($event->getKey()), ['values' => ['title' => ['en' => 'The nearest, renamed'], 'slug' => ['en' => 'the-nearest']]])
            ->assertOk();
        $this->actingAs($editor, 'cms')->postJson($this->api($event->getKey().'/publish'))->assertOk();

        $this->get('/events')->assertOk()->assertSee('The nearest, renamed')->assertDontSee('>Event 1<', false);
        $this->get('/events/the-nearest')->assertOk()->assertSee('The nearest, renamed');
        $this->get('/events/event-1')->assertRedirect('/events/the-nearest');
    }

    /**
     * Three categories, four pictures, two services and thirty events to come, one a day: each
     * with a gallery of two (the cover differs from card to card, so a lookup per cover would
     * show), a category, a place, a price and highlights, every second one related to the
     * services — the way the demo has them.
     */
    private function seedEvents(): void
    {
        // A fixed moment, so that "to come" means the same on every run.
        Carbon::setTestNow('2026-10-17 12:00:00');

        $categories = [$this->category('cooking-classes'), $this->category('tastings'), $this->category('tours')];
        $pictures = array_map(fn (string $name): MediaFile => $this->picture("media/ab/cd/{$name}.jpg"), ['one', 'two', 'three', 'four']);
        $services = [$this->service('catering'), $this->service('venue-hire')];

        for ($i = 1; $i <= self::EVENTS; $i++) {
            $event = $this->event("event-{$i}", Carbon::now()->addDays($i)->setTime(10, 0), attributes: [
                'lead' => "The lead of event {$i}.",
                'gallery' => [['path' => $pictures[$i % 4]->path], ['path' => $pictures[($i + 1) % 4]->path]],
                'venue' => 'The Hall',
                'address' => '1 Main Street',
                'price' => 'from 20',
                'price_amount' => 20,
                'booking_url' => 'https://example.test/book',
                'description' => "<p>What event {$i} is about.</p>",
                'highlights' => [['title' => ['en' => 'Hands on'], 'text' => ['en' => 'Everybody cooks.']]],
            ]);
            $event->syncCategories([$categories[$i % 3]->getKey()]);

            if ($i % 2 === 0) {
                $event->syncRelated(Event::SERVICES, 'service', [$services[0]->getKey(), $services[1]->getKey()]);
            }
        }
    }

    /** The first visitor after a deploy: an empty store, and nothing read in this process either. */
    private function cold(): void
    {
        Cache::flush();
        $this->app->make(CacheGroups::class)->forgetMemo();
    }
}
