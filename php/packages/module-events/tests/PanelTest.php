<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Events\Models\Event;
use WebxUi\Routing\Models\Route;

/**
 * The API the panel is written against (§4.10) — the shapes are a contract with the npm half,
 * which was written in parallel from the spec, so the tests read the keys one by one.
 *
 * In an application whose timezone is not UTC: in UTC every mistake about offsets cancels out
 * (CLAUDE.md §4).
 */
final class PanelTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Both, the way an application boots: Laravel sets PHP's zone from the config, and
        // Eloquent reads a column in PHP's zone.
        $app['config']->set('app.timezone', 'Asia/Hong_Kong');
        date_default_timezone_set('Asia/Hong_Kong');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        date_default_timezone_set('UTC');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Hong_Kong'));
    }

    #[Test]
    public function the_list_is_a_page_of_rows_with_what_it_can_be_narrowed_to(): void
    {
        $classes = $this->category('cooking-classes');
        $implants = $this->service('implants');

        $event = $this->event('dumplings', '2026-10-12T10:00:00+08:00', attributes: ['ends_at' => '2026-10-12T12:30:00+08:00']);
        $event->syncCategories([$classes->id]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertSame(['data', 'links', 'meta', 'filters'], array_keys((array) $response->json()));

        $row = $response->json('data.0');

        $this->assertSame([
            'id', 'title', 'slug', 'path', 'url', 'cover', 'starts_at', 'ends_at', 'all_day', 'when', 'past',
            'status', 'categories', 'published_at', 'updated_at', 'deleted_at', 'revision',
        ], array_keys($row));
        $this->assertSame('Dumplings', $row['title']);
        $this->assertSame('events/dumplings', $row['path']);
        $this->assertSame('2026-10-12T10:00:00+08:00', $row['starts_at']);
        $this->assertSame('2026-10-12T12:30:00+08:00', $row['ends_at']);
        $this->assertFalse($row['all_day']);
        $this->assertSame('12 October 2026, 10:00–12:30', $row['when']);
        $this->assertFalse($row['past']);
        $this->assertSame('published', $row['status']);
        $this->assertSame([['id' => $classes->id, 'title' => 'Cooking classes']], $row['categories']);

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertSame([['id' => $classes->id, 'title' => 'Cooking classes']], $response->json('filters.categories'));
        $this->assertSame([['id' => $implants->id, 'title' => 'Implants']], $response->json('filters.services'));
    }

    #[Test]
    public function when_is_upcoming_by_default_past_on_asking_and_all_on_asking(): void
    {
        $this->event('later', '2026-10-20 10:00:00');
        $this->event('sooner', '2026-10-12 10:00:00');
        $this->event('saturdays');
        $this->event('over', '2026-09-01 10:00:00');
        $this->event('long-ago', '2026-01-01 10:00:00');
        $this->event('thrown', '2026-10-15 10:00:00')->delete();

        $editor = $this->editor();
        $titles = fn (string $query): array => array_column(
            (array) $this->actingAs($editor, 'cms')->getJson($this->api().$query)->assertOk()->json('data'),
            'title',
        );

        $this->assertSame(['Saturdays', 'Sooner', 'Later'], $titles(''));
        $this->assertSame(['Saturdays', 'Sooner', 'Later'], $titles('?when=upcoming'));
        $this->assertSame(['Over', 'Long ago'], $titles('?when=past'));
        $this->assertSame(['Saturdays', 'Later', 'Sooner', 'Over', 'Long ago'], $titles('?when=all'));
        $this->assertSame(['Thrown'], $titles('?when=all&trashed=1'));
        $this->assertSame(['Long ago'], $titles('?when=past&q=long'));
    }

    #[Test]
    public function the_list_is_narrowed_by_category_service_and_status_and_cut_into_pages(): void
    {
        $classes = $this->category('cooking-classes');
        $implants = $this->service('implants');

        $this->event('dumplings', '2026-10-12 10:00:00')->syncCategories([$classes->id]);
        $this->event('talk', '2026-10-13 10:00:00')->syncRelated(Event::SERVICES, 'service', [$implants->id]);
        $this->event('draft', '2026-10-14 10:00:00', published: false);
        $edited = $this->event('edited', '2026-10-15 10:00:00');
        $edited->saveDraft(['title' => ['en' => 'Edited again']]);

        $editor = $this->editor();
        $titles = fn (string $query): array => array_column(
            (array) $this->actingAs($editor, 'cms')->getJson($this->api().$query)->assertOk()->json('data'),
            'title',
        );

        $this->assertSame(['Dumplings'], $titles('?category='.$classes->id));
        $this->assertSame(['Talk'], $titles('?service='.$implants->id));
        $this->assertSame(['Draft'], $titles('?status=draft'));
        // Live is live, with edits waiting or without.
        $this->assertSame(['Dumplings', 'Talk', 'Edited again'], $titles('?status=published'));
        $this->assertSame(['Edited again'], $titles('?status=modified'));

        $page = $this->actingAs($editor, 'cms')->getJson($this->api().'?per_page=5&page=1')->assertOk();
        $this->assertSame(4, $page->json('meta.total'));
        $this->assertSame(5, $page->json('meta.per_page'));
        $this->assertNull($page->json('links.next'));
    }

    #[Test]
    public function a_new_event_is_a_draft_with_its_address_held_and_the_form_around_it(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['title' => 'Spring class'])
            ->assertCreated();

        $this->assertSame(['event', 'values', 'revision', 'prefix', 'preview_url'], array_keys((array) $response->json('data')));
        $this->assertSame('draft', $response->json('data.event.status'));
        $this->assertSame('events/spring-class', $response->json('data.event.path'));
        $this->assertSame('events', $response->json('data.prefix'));
        $this->assertStringContainsString('_preview/event/', (string) $response->json('data.preview_url'));

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        $this->assertSame(['en' => 'Spring class'], $values['title']);
        $this->assertNull($values['starts_at']);
        $this->assertNull($values['ends_at']);
        $this->assertFalse($values['all_day']);
        $this->assertSame('offline', $values['attendance']);
        $this->assertNull($values['price_amount']);
        $this->assertSame([], $values['gallery']);
        $this->assertSame([], $values['highlights']);
        $this->assertSame([], $values['categories']);
        $this->assertSame([], $values['services']);
    }

    #[Test]
    public function an_address_somebody_holds_refuses_the_new_event_and_leaves_no_row(): void
    {
        $this->category('cooking-classes');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['title' => 'Cooking classes'])
            ->assertUnprocessable();

        $this->assertSame(0, Event::withTrashed()->count());
    }

    #[Test]
    public function the_editor_saves_every_field_and_a_moment_keeps_its_instant(): void
    {
        $this->useLocales('en', 'ru');

        $event = $this->event('class');
        $picture = $this->picture();
        $classes = $this->category('cooking-classes');
        $implants = $this->service('implants');
        $editor = $this->editor();

        $revision = $this->actingAs($editor, 'cms')->getJson($this->api($event->id))->json('data.revision');

        $response = $this->actingAs($editor, 'cms')->putJson($this->api($event->id), [
            'revision' => $revision,
            'values' => [
                'lead' => ['en' => 'Three hours of dumplings.'],
                'gallery' => [['path' => $picture->path, 'url' => 'https://elsewhere.test/stale.jpg']],
                // Written in Moscow; the application is in Hong Kong.
                'starts_at' => '2026-10-12T10:00:00+03:00',
                'ends_at' => '2026-10-12T12:30:00+03:00',
                'all_day' => false,
                'date_note' => ['en' => ''],
                'attendance' => 'mixed',
                'venue' => ['en' => 'Studio Kitchen', 'ru' => 'Студия'],
                'address' => ['en' => '1 Queen Road'],
                'map_url' => 'https://maps.example.test/studio',
                'description' => ['en' => '<p>Bring an apron.</p>'],
                'highlights' => [
                    ['title' => ['en' => 'Hands on', 'ru' => 'Руками'], 'text' => ['en' => 'You cook.', 'ru' => 'Готовите сами.']],
                ],
                'price' => ['en' => 'HK$480 per person'],
                'price_amount' => 480,
                'booking_url' => 'https://book.example.test/spring',
                'categories' => [$classes->id],
                'services' => [$implants->id],
            ],
        ])->assertOk();

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        // The same instant, told in the application's zone: 07:00 UTC.
        $this->assertSame('2026-10-12T15:00:00+08:00', $values['starts_at']);
        $this->assertSame('2026-10-12T17:30:00+08:00', $values['ends_at']);
        $this->assertSame([['path' => $picture->path]], $values['gallery']);
        $this->assertSame('mixed', $values['attendance']);
        $this->assertSame(['en' => 'Studio Kitchen', 'ru' => 'Студия'], $values['venue']);
        $this->assertSame([
            ['title' => ['en' => 'Hands on', 'ru' => 'Руками'], 'text' => ['en' => 'You cook.', 'ru' => 'Готовите сами.']],
        ], $values['highlights']);
        $this->assertEquals(480, $values['price_amount']);
        $this->assertSame('https://book.example.test/spring', $values['booking_url']);
        $this->assertSame([$classes->id], $values['categories']);
        $this->assertSame([$implants->id], $values['services']);
        $this->assertSame('modified', $response->json('data.event.status'));
        $this->assertSame('2026-10-12T15:00:00+08:00', $response->json('data.event.starts_at'));

        // Published, the columns hold the same instant, and the categories and services arrive.
        $event->refresh()->publish();
        $event->refresh();

        $this->assertSame('2026-10-12 15:00:00', $event->getRawOriginal('starts_at'));
        $this->assertSame([$classes->id], $event->categoryIds());
        $this->assertSame([$implants->id], $event->relatedIds(Event::SERVICES));
        $this->assertSame([['title' => 'Руками', 'text' => 'Готовите сами.']], $event->highlights('ru'));
    }

    #[Test]
    public function an_event_of_days_keeps_the_days_the_editor_picked_whatever_their_offset(): void
    {
        $event = $this->event('festival');

        // Picked in Moscow as 12 and 14 October; midnight there is still 11 October in UTC.
        $response = $this->actingAs($this->editor(), 'cms')->putJson($this->api($event->id), ['values' => [
            'all_day' => true,
            'starts_at' => '2026-10-12T00:00:00+03:00',
            'ends_at' => '2026-10-14T00:00:00+03:00',
        ]])->assertOk();

        $this->assertSame('2026-10-12T00:00:00+08:00', $response->json('data.values.starts_at'));
        $this->assertSame('2026-10-14T23:59:59+08:00', $response->json('data.values.ends_at'));
        $this->assertSame('12–14 October 2026', $response->json('data.event.when'));
    }

    #[Test]
    public function an_end_before_the_start_or_without_one_is_refused_under_the_end(): void
    {
        $event = $this->event('class');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->putJson($this->api($event->id), ['values' => [
            'starts_at' => '2026-10-12T10:00:00+08:00',
            'ends_at' => '2026-10-12T09:00:00+08:00',
        ]])->assertUnprocessable()->assertJsonValidationErrors(['ends_at']);

        $this->actingAs($editor, 'cms')->putJson($this->api($event->id), ['values' => [
            'ends_at' => '2026-10-12T09:00:00+08:00',
        ]])->assertUnprocessable()->assertJsonValidationErrors(['ends_at']);

        $this->actingAs($editor, 'cms')->putJson($this->api($event->id), ['values' => [
            'booking_url' => 'javascript:alert(1)',
        ]])->assertUnprocessable()->assertJsonValidationErrors(['booking_url']);

        $this->assertFalse($event->refresh()->hasDraft());
    }

    #[Test]
    public function a_save_over_somebody_elses_is_refused(): void
    {
        $event = $this->event('class');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($event->id), ['revision' => 'stale', 'values' => ['lead' => ['en' => 'x']]])
            ->assertStatus(409)
            ->assertJsonPath('data.event.id', $event->id);
    }

    #[Test]
    public function a_copy_is_a_draft_with_everything_but_the_address_and_the_history(): void
    {
        $this->useLocales('en', 'ru');

        $classes = $this->category('cooking-classes');
        $implants = $this->service('implants');

        $source = $this->event('class', '2026-10-12 10:00:00', attributes: [
            'title' => ['en' => 'Class', 'ru' => 'Класс'],
            'slug' => ['en' => 'class', 'ru' => 'klass'],
            'price' => ['en' => 'HK$480'],
            'highlights' => [['title' => ['en' => 'Hands on'], 'text' => ['en' => 'You cook.']]],
        ]);
        $source->syncCategories([$classes->id]);
        $source->syncRelated(Event::SERVICES, 'service', [$implants->id]);
        $source->saveSeo(['title' => ['en' => 'Class — SEO']]);
        // Somebody already made a copy once.
        $this->event('class-2', '2026-10-19 10:00:00');

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api($source->id.'/duplicate'))->assertCreated();

        $copy = Event::query()->findOrFail($response->json('data.event.id'));

        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame('draft', $response->json('data.event.status'));
        $this->assertSame(['en' => 'Class', 'ru' => 'Класс'], $copy->getTranslations('title'));
        $this->assertSame(['en' => 'class-3', 'ru' => 'klass-2'], $copy->getTranslations('slug'));
        $this->assertSame('events/class-3', $copy->routeCanonical('en')?->path);
        $this->assertSame('2026-10-12 10:00:00', $copy->getRawOriginal('starts_at'));
        $this->assertSame(['en' => 'HK$480'], $copy->getTranslations('price'));
        $this->assertSame($source->highlights, $copy->highlights);
        $this->assertSame([$classes->id], $copy->categoryIds());
        $this->assertSame([$implants->id], $copy->relatedIds(Event::SERVICES));
        $this->assertSame('Class — SEO', $copy->seoValue()['title']['en'] ?? null);
        $this->assertFalse($copy->isPublished());
        $this->assertSame(0, $copy->versions()->count());
    }

    #[Test]
    public function a_copy_refused_half_way_leaves_no_row(): void
    {
        $source = $this->event('class', '2026-10-12 10:00:00');
        $before = Event::withTrashed()->count();

        // A category takes the address the copy is about to be given, behind the check's back.
        Route::query()->where('path', 'events/class-2')->delete();
        $category = $this->category('class-2');
        Route::query()->where('path', 'events/class-2')->delete();

        $this->app['events']->listen('eloquent.saving: '.Event::class, static function (Event $event) use ($category): void {
            if ($event->exists === false) {
                Route::query()->create([
                    'locale' => 'en',
                    'path' => 'events/class-2',
                    'kind' => Route::CANONICAL,
                    'entity_type' => $category->getMorphClass(),
                    'entity_id' => $category->id,
                ]);
            }
        });

        $this->actingAs($this->editor(), 'cms')->postJson($this->api($source->id.'/duplicate'))->assertUnprocessable();

        $this->assertSame($before, Event::withTrashed()->count());
    }

    #[Test]
    public function the_screens_are_described_with_the_seo_card_patched_in(): void
    {
        $editor = $this->editor();

        $root = (array) $this->actingAs($editor, 'cms')->getJson('/api/cms/screens/events.form')->assertOk()->json('data.root');
        $tabs = $root[0]['children'];

        $this->assertSame(['event', 'settings', 'seo', 'history'], array_column($tabs, 'id'));
        $this->assertSame('seo-card', $tabs[2]['children'][0]['id']);

        $this->actingAs($editor, 'cms')->getJson('/api/cms/screens/events.category-form')->assertOk();
        $this->actingAs($editor, 'cms')->getJson('/api/cms/events/categories')->assertOk();
    }
}
