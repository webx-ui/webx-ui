<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Events\EventsServiceProvider;
use WebxUi\Events\Models\Event;

/**
 * The public half (§4.4, §4.5, §4.8): lists of the events to come only, a page at a time; the page
 * of an event, past or not; "What to expect" in the language of the page; the helper.
 */
final class PageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));
    }

    #[Test]
    public function the_index_lists_only_the_ones_to_come_the_undated_first(): void
    {
        $this->event('later', '2026-10-20 10:00:00');
        $this->event('sooner', '2026-10-12 10:00:00');
        $this->event('saturdays', attributes: ['date_note' => 'Every Saturday']);
        $this->event('over', '2026-09-01 10:00:00');
        $this->event('draft', '2026-10-15 10:00:00', published: false);

        $page = (string) $this->get('/events')->assertOk()->getContent();

        $this->assertSame(['Saturdays', 'Sooner', 'Later'], $this->names($page));
        $this->assertSame(3, count($this->jsonLd($page, 'ItemList')['itemListElement']));
    }

    #[Test]
    public function an_empty_list_is_a_page_that_says_so_and_a_page_past_the_last_is_404(): void
    {
        $this->app['config']->set('webx-events.per-page', 2);

        $this->get('/events')->assertOk()->assertSee('No events coming up.');

        foreach (['a', 'b', 'c'] as $offset => $slug) {
            $this->event($slug, '2026-10-2'.$offset.' 10:00:00');
        }

        $this->assertSame(['A', 'B'], $this->names((string) $this->get('/events')->getContent()));
        $this->assertSame(['C'], $this->names((string) $this->get('/events?page=2')->assertSee('rel="prev"', false)->getContent()));
        $this->get('/events?page=3')->assertNotFound();
    }

    #[Test]
    public function a_category_page_lists_its_own_events_to_come(): void
    {
        $classes = $this->category('cooking-classes');
        $breakfasts = $this->category('breakfasts');

        $this->event('dumplings', '2026-10-12 10:00:00')->syncCategories([$classes->id]);
        $this->event('noodles', '2026-09-12 10:00:00')->syncCategories([$classes->id]);
        $this->event('eggs', '2026-10-13 09:00:00')->syncCategories([$breakfasts->id]);

        $page = (string) $this->get('/events/cooking-classes')->assertOk()->getContent();

        $this->assertSame(['Dumplings'], $this->names($page));

        $this->category('hidden', visible: false);
        $this->get('/events/hidden')->assertNotFound();
    }

    #[Test]
    public function a_past_event_keeps_its_page_and_says_it_is_over(): void
    {
        $this->event('over', '2026-09-01 10:00:00', attributes: ['booking_url' => 'https://book.example.test']);

        $this->get('/events/over')
            ->assertOk()
            ->assertSee('This event is over.')
            ->assertDontSee('wx-event__book', false);
    }

    #[Test]
    public function what_to_expect_is_printed_in_the_language_of_the_page(): void
    {
        $this->useLocales('en', 'ru');

        $this->event('class', '2026-10-12 10:00:00', attributes: [
            'title' => ['en' => 'Class', 'ru' => 'Класс'],
            'slug' => ['en' => 'class', 'ru' => 'klass'],
            'highlights' => [
                ['title' => ['en' => 'Hands on', 'ru' => 'Руками'], 'text' => ['en' => 'You cook.', 'ru' => 'Готовите сами.']],
                // Only in English: not a card on the Russian page, and no English there either.
                ['title' => ['en' => 'Tasting'], 'text' => ['en' => 'You eat.']],
            ],
        ]);

        $this->get('/events/class')->assertOk()->assertSee('Hands on')->assertSee('Tasting');

        $this->get('/ru/events/klass')
            ->assertOk()
            ->assertSee('Руками')
            ->assertSee('Готовите сами.')
            ->assertDontSee('Tasting');
    }

    #[Test]
    public function an_online_event_prints_no_place(): void
    {
        $this->event('webinar', '2026-10-12 10:00:00', attributes: [
            'attendance' => Event::ONLINE,
            'venue' => 'Studio Kitchen',
            'address' => '1 Queen Road',
        ]);

        $this->get('/events/webinar')->assertOk()->assertSee('Online')->assertDontSee('Studio Kitchen')->assertDontSee('1 Queen Road');
    }

    #[Test]
    public function the_services_of_an_event_are_printed_on_it(): void
    {
        $implants = $this->service('implants');
        $event = $this->event('class', '2026-10-12 10:00:00');
        $event->syncRelated(Event::SERVICES, 'service', [$implants->id]);

        $this->get('/events/class')->assertOk()->assertSee('Implants');
    }

    #[Test]
    public function the_helper_gives_cards_to_come_past_or_all(): void
    {
        $classes = $this->category('cooking-classes');
        $sooner = $this->event('sooner', '2026-10-12 10:00:00', attributes: ['price' => 'HK$480']);
        $sooner->syncCategories([$classes->id]);
        $this->event('later', '2026-10-20 10:00:00');
        $this->event('over', '2026-09-01 10:00:00');
        $this->event('long-ago', '2026-01-01 10:00:00');

        $this->assertSame(['Sooner', 'Later'], array_column(events()->get(), 'title'));
        $this->assertSame(['Over', 'Long ago'], array_column(events()->past()->get(), 'title'));
        $this->assertSame(['Later', 'Sooner', 'Over', 'Long ago'], array_column(events()->all()->get(), 'title'));
        $this->assertSame(['Sooner'], array_column(events()->in('cooking-classes')->get(), 'title'));
        $this->assertSame(['Over'], array_column(events()->past()->take(1)->get(), 'title'));

        $card = events()->first();
        $this->assertIsArray($card);

        $this->assertSame([
            'id', 'url', 'title', 'lead', 'cover', 'gallery', 'starts_at', 'ends_at', 'all_day', 'when', 'past',
            'attendance', 'venue', 'price', 'booking_url', 'ics_url', 'categories', 'category_links', 'fields',
        ], array_keys($card));
        $this->assertSame('12 October 2026, 10:00', $card['when']);
        $this->assertSame('2026-10-12T10:00:00+00:00', $card['starts_at']);
        $this->assertFalse($card['past']);
        $this->assertSame('HK$480', $card['price']);
        $this->assertStringEndsWith('/events/sooner.ics', (string) $card['ics_url']);
        $this->assertSame([$classes->id], $card['categories']);
    }

    #[Test]
    public function the_sitemap_names_past_events_too(): void
    {
        $this->event('over', '2026-09-01 10:00:00');

        $this->event('draft', '2026-10-12 10:00:00', published: false);

        $sitemap = (string) $this->get('/sitemap-event.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/events/over', $sitemap);
        $this->assertStringNotContainsString('/events/draft', $sitemap);
        $this->assertStringContainsString('/events<', (string) $this->get('/sitemap-routes.xml')->assertOk()->getContent());
    }

    #[Test]
    public function an_empty_prefix_is_refused(): void
    {
        $this->app['config']->set('webx-events.prefix', ' / ');

        $this->expectException(InvalidArgumentException::class);

        EventsServiceProvider::prefix($this->app['config']);
    }

    /** @return list<string> */
    private function names(string $page): array
    {
        preg_match_all('#<span class="wx-events__name">(.*?)</span>#', $page, $matches);

        return $matches[1];
    }
}
