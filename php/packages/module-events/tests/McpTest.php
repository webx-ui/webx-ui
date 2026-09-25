<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Events\Models\Event;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Routing\Models\Route;

/**
 * Events by their other doors (§4.11): the same list, the same screen, the same draft, the same
 * copy as the panel's "Duplicate".
 */
final class McpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 12:00:00');
    }

    #[Test]
    public function the_two_sections_offer_their_tools_and_the_catalogue(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['events_list', 'events_get', 'events_create', 'events_update', 'events_duplicate', 'events_publish', 'events_unpublish', 'events_delete'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('events')),
        );

        $this->assertSame(
            ['event_categories_list', 'event_categories_create', 'event_categories_update', 'event_categories_delete', 'event_categories_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('event-categories')),
        );

        $this->assertSame(['events.view', 'events.manage'], $registry->tool('events_list')->permissions());
        $this->assertSame(['events.manage'], $registry->tool('events_duplicate')->permissions());
        $this->assertSame(['events.categories.manage'], $registry->tool('event_categories_create')->permissions());

        // The dates are what an agent gets wrong untold: the format, and the zone of a bare one.
        foreach (['events_create', 'events_update'] as $tool) {
            $this->assertStringContainsString('ISO 8601', $registry->tool($tool)->tool->description);
            $this->assertStringContainsString('UTC', $registry->tool($tool)->tool->description);
        }

        $this->assertContains('events://catalog', array_map(static fn ($resource): string => $resource->uri, $registry->resources()));
    }

    #[Test]
    public function a_reader_lists_and_is_refused_a_write(): void
    {
        $reader = $this->editor(['events.view']);

        $this->agent('events_list', [], $reader)->assertOk();
        $this->agent('events_create', ['title' => 'Class'], $reader)->assertHasErrors(['[events.manage]']);
    }

    #[Test]
    public function create_writes_a_draft_and_the_links_wait_for_publishing(): void
    {
        $classes = $this->category('cooking-classes');
        $plan = $this->service('nutrition-plan');

        $created = $this->content($this->agent('events_create', [
            'title' => 'Spring cooking class',
            'values' => [
                'lead' => 'Three dishes.',
                'starts_at' => '2026-10-12T10:00:00+03:00',
                // No offset: the site's zone.
                'ends_at' => '2026-10-12T09:30:00',
                'price' => 'HK$480 per person',
                'price_amount' => 480,
                'highlights' => [['title' => 'Your own stove', 'text' => 'Every guest cooks.']],
                'categories' => ['events/cooking-classes'],
                'services' => ['/services/nutrition-plan'],
            ],
        ]));

        $this->assertSame('draft', $created['event']['status']);
        $this->assertSame('/events/spring-cooking-class', $created['event']['urls']['en']['path']);
        $this->assertSame(['en' => 'Three dishes.'], $created['values']['lead']);
        $this->assertSame('2026-10-12T07:00:00+00:00', $created['values']['starts_at']);
        $this->assertSame('2026-10-12T09:30:00+00:00', $created['values']['ends_at']);
        $this->assertSame([['title' => ['en' => 'Your own stove'], 'text' => ['en' => 'Every guest cooks.']]], $created['values']['highlights']);
        $this->assertSame([$classes->id], $created['values']['categories']);
        $this->assertSame([$plan->id], $created['values']['services']);

        $event = Event::query()->findOrFail($created['event']['id']);

        // Everything waits in the draft: nothing is linked on the site yet.
        $this->assertSame([], $event->relatedIds(Event::SERVICES));
        $this->assertSame([], $event->categoryIds());

        $published = $this->content($this->agent('events_publish', ['event' => $event->id]));

        $this->assertSame('published', $published['event']['status']);
        $event->refresh();
        $this->assertSame([$plan->id], $event->relatedIds(Event::SERVICES));
        $this->assertSame([$classes->id], $event->categoryIds());
    }

    #[Test]
    public function an_event_of_days_is_written_as_its_days(): void
    {
        $created = $this->content($this->agent('events_create', [
            'title' => 'Fermentation intensive',
            'values' => ['all_day' => true, 'starts_at' => '2026-10-12', 'ends_at' => '2026-10-14'],
        ]));

        $this->assertTrue($created['values']['all_day']);
        $this->assertSame('2026-10-12T00:00:00+00:00', $created['values']['starts_at']);
        $this->assertSame('2026-10-14T23:59:59+00:00', $created['values']['ends_at']);
    }

    #[Test]
    public function a_refused_create_leaves_nothing_behind(): void
    {
        $this->agent('events_create', [
            'title' => 'Class',
            'values' => ['services' => [999]],
        ])->assertHasErrors(['[999]']);

        $this->agent('events_create', [
            'title' => 'Class',
            'values' => ['starts_at' => '2026-10-12T10:00:00+00:00', 'ends_at' => '2026-10-11T10:00:00+00:00'],
        ])->assertHasErrors(['ends_at']);

        $this->agent('events_create', ['title' => 'Class', 'values' => ['starts_at' => 'next Tuesdayish']])
            ->assertHasErrors(['ISO 8601']);
        $this->agent('events_create', ['title' => 'Class', 'values' => ['attendance' => 'hybrid']])
            ->assertHasErrors(['attendance']);

        $this->assertSame(0, Event::withTrashed()->count());
        $this->assertSame(0, Route::query()->where('path', 'events/class')->count());
    }

    #[Test]
    public function update_names_services_by_address_and_refuses_blocks(): void
    {
        $event = $this->event('class', '2026-10-12 10:00:00');
        $plan = $this->service('nutrition-plan');

        $this->agent('events_update', ['event' => '/events/class', 'values' => ['services' => ['/services/nothing-here']]])
            ->assertHasErrors(['/services/nothing-here']);
        $this->agent('events_update', ['event' => $event->id, 'values' => ['blocks' => []]])
            ->assertHasErrors(['no blocks']);

        $got = $this->content($this->agent('events_update', [
            'event' => '/events/class',
            'values' => ['services' => [(string) $plan->id], 'date_note' => 'Every Saturday'],
        ]));

        $this->assertSame([$plan->id], $got['values']['services']);
        $this->assertSame('modified', $got['event']['status']);
        $this->assertSame('Every Saturday', $got['event']['when']);
    }

    #[Test]
    public function a_write_over_somebody_elses_is_refused(): void
    {
        $event = $this->event('class', '2026-10-12 10:00:00');
        $revision = $this->content($this->agent('events_get', ['event' => $event->id]))['revision'];

        $this->agent('events_update', ['event' => $event->id, 'values' => ['lead' => 'First.'], 'revision' => $revision])->assertOk();
        $this->agent('events_update', ['event' => $event->id, 'values' => ['lead' => 'Second.'], 'revision' => $revision])
            ->assertHasErrors(['changed since you read it']);
    }

    #[Test]
    public function duplicate_is_the_panels_copy(): void
    {
        $classes = $this->category('cooking-classes');
        $event = $this->event('class', '2026-10-12 10:00:00');
        $event->syncCategories([$classes->id]);

        $this->agent('events_duplicate', ['event' => $event->id, 'dry_run' => true])->assertOk();
        $this->assertSame(1, Event::query()->count());

        $copy = $this->content($this->agent('events_duplicate', ['event' => '/events/class']));

        $this->assertSame($event->id, $copy['copied_from']);
        $this->assertSame('draft', $copy['event']['status']);
        $this->assertSame('/events/class-2', $copy['event']['urls']['en']['path']);
        $this->assertSame([$classes->id], $copy['values']['categories']);
        $this->assertSame('2026-10-12T10:00:00+00:00', $copy['values']['starts_at']);
    }

    #[Test]
    public function the_list_is_the_events_to_come_unless_asked_otherwise(): void
    {
        $classes = $this->category('cooking-classes');
        $plan = $this->service('nutrition-plan');

        $soon = $this->event('soon', '2026-10-05 10:00:00');
        $soon->syncCategories([$classes->id]);
        $later = $this->event('later', '2026-11-05 10:00:00');
        $later->syncRelated(Event::SERVICES, 'service', [$plan->id]);
        $undated = $this->event('undated');
        $over = $this->event('over', '2026-09-05 10:00:00');
        $this->event('draft', '2026-10-20 10:00:00', published: false);

        $ids = fn (array $arguments): array => array_column($this->content($this->agent('events_list', $arguments))['events'], 'id');

        $this->assertSame($undated->id, $ids([])[0]);
        $this->assertSame([$undated->id, $soon->id], array_slice($ids([]), 0, 2));
        $this->assertNotContains($over->id, $ids([]));
        $this->assertSame([$over->id], $ids(['when' => 'past']));
        $this->assertCount(5, $ids(['when' => 'all']));
        $this->assertSame([$soon->id], $ids(['category' => 'cooking-classes']));
        $this->assertSame([$later->id], $ids(['service' => '/services/nutrition-plan']));
        $this->assertCount(1, $ids(['status' => 'draft']));
        $this->agent('events_list', ['when' => 'soon'])->assertHasErrors(['upcoming']);

        $page = $this->content($this->agent('events_list', ['when' => 'all']));
        $this->assertSame(['page' => 1, 'pages' => 1, 'total' => 5], ['page' => $page['page'], 'pages' => $page['pages'], 'total' => $page['total']]);
    }

    #[Test]
    public function unpublish_and_delete(): void
    {
        $event = $this->event('class', '2026-10-12 10:00:00');

        $this->assertSame('unpublished', $this->content($this->agent('events_unpublish', ['event' => $event->id]))['event']['status']);

        $this->agent('events_delete', ['event' => $event->id, 'dry_run' => true])->assertOk();
        $this->assertFalse($event->refresh()->trashed());

        $this->agent('events_delete', ['event' => '/events/class'])->assertOk();
        $this->assertTrue($event->refresh()->trashed());
        $this->assertSame([$event->id], array_column($this->content($this->agent('events_list', ['trashed' => true]))['events'], 'id'));
        $this->agent('events_update', ['event' => $event->id, 'values' => ['lead' => 'x']])->assertHasErrors(['in the bin']);
        $this->agent('events_duplicate', ['event' => $event->id])->assertHasErrors(['in the bin']);
    }

    #[Test]
    public function the_catalogue_lists_the_events_to_come_and_counts_the_past(): void
    {
        $this->useLocales('en', 'ru');

        $classes = $this->category('cooking-classes');
        $hidden = $this->category('secret', visible: false);
        $both = $this->event('class', '2026-10-12 10:00:00', attributes: ['title' => ['en' => 'Class', 'ru' => 'Класс']]);
        $both->syncCategories([$classes->id, $hidden->id]);
        $undated = $this->event('club', attributes: ['date_note' => ['en' => 'Every Saturday']]);
        $undated->syncCategories([$classes->id]);
        $this->event('over', '2026-09-05 10:00:00')->syncCategories([$classes->id]);
        $loose = $this->event('loose', '2026-10-20 10:00:00');
        $this->event('loose-over', '2026-09-01 10:00:00');

        $catalog = ($this->resource('events://catalog')->handler)();

        $this->assertSame('events', $catalog['prefix']);
        $this->assertSame('UTC', $catalog['timezone']);
        $this->assertStringEndsWith('/events', (string) $catalog['index_url']);
        $this->assertSame(['Cooking classes', 'Secret'], array_column($catalog['categories'], 'title'));
        $this->assertFalse($catalog['categories'][1]['visible']);
        // Without a date first, then from the nearest; the past one is only counted.
        $this->assertSame([$undated->id, $both->id], array_column($catalog['categories'][0]['upcoming'], 'id'));
        $this->assertSame(1, $catalog['categories'][0]['past_count']);
        $this->assertSame('Every Saturday', $catalog['categories'][0]['upcoming'][0]['when']);
        $this->assertSame(['en', 'ru'], $catalog['categories'][0]['upcoming'][1]['written_in']);
        $this->assertStringEndsWith('/events/class', (string) $catalog['categories'][0]['upcoming'][1]['url']);
        $this->assertSame([$both->id], array_column($catalog['categories'][1]['upcoming'], 'id'));
        $this->assertSame([$loose->id], array_column($catalog['uncategorised']['upcoming'], 'id'));
        $this->assertSame(1, $catalog['uncategorised']['past_count']);
    }

    #[Test]
    public function the_catalogue_has_no_index_when_it_is_switched_off(): void
    {
        $this->app['config']->set('webx-events.index', false);

        $this->assertNull(($this->resource('events://catalog')->handler)()['index_url']);
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No resource [{$uri}].");
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
