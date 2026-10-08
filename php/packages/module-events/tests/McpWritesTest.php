<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Panel\EventWriter;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Routing\Models\Route;

/**
 * What the event tools refuse, what a dry run proves, and the way back out of the bin: a tool
 * that drops what it does not know, or a dry run that says yes where the call says no, tells an
 * agent it did something it did not.
 */
final class McpWritesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 12:00:00');
    }

    #[Test]
    public function a_field_or_an_argument_the_tool_does_not_know_is_refused(): void
    {
        $this->agent('events_create', ['title' => 'Class', 'values' => ['unknown_field_x' => 1]])
            ->assertHasErrors(['events_create has no field [unknown_field_x]']);
        $this->assertSame(0, Event::withTrashed()->count());

        $event = $this->event('class', '2026-10-12 10:00:00');

        $this->agent('events_update', ['event' => $event->id, 'values' => ['unknown_field_x' => 1]])
            ->assertHasErrors(['events_update has no field [unknown_field_x]']);
        $this->agent('events_update', ['event' => $event->id, 'values' => ['lead' => 'x'], 'colour' => 'red'])
            ->assertHasErrors(['events_update has no argument [colour]']);

        $this->assertFalse($event->refresh()->hasDraft());
    }

    #[Test]
    public function a_projects_field_is_in_the_values_before_anybody_writes_it(): void
    {
        $this->app->make(ScreenRegistry::class)->extend(Event::SCREEN, [[
            'op' => 'add',
            'target' => 'tabs',
            'node' => [
                'id' => 'extra-tab',
                'type' => 'wx-tab',
                'children' => [['id' => 'icon', 'type' => 'wx-input', 'name' => 'icon']],
            ],
        ]]);

        $event = $this->event('class', '2026-10-12 10:00:00');
        $values = $this->content($this->agent('events_get', ['event' => $event->id]))['values'];

        $this->assertArrayHasKey('icon', $values);
        $this->assertNull($values['icon']);

        $written = $this->content($this->agent('events_update', ['event' => $event->id, 'values' => ['icon' => 'star'], 'force' => true]));
        $this->assertSame('star', $written['values']['icon']);
    }

    #[Test]
    public function a_copy_says_it_has_everything_still_to_publish(): void
    {
        $event = $this->event('class', '2026-10-12 10:00:00');

        $copy = $this->content($this->agent('events_duplicate', ['event' => $event->id]));

        $this->assertSame('draft', $copy['event']['status']);
        $this->assertTrue($copy['event']['has_draft']);

        // A published event without edits waiting has nothing to publish.
        $this->assertFalse($this->content($this->agent('events_get', ['event' => $event->id]))['event']['has_draft']);
    }

    #[Test]
    public function a_dry_run_is_refused_where_the_call_is_and_changes_nothing(): void
    {
        $event = $this->event('class', '2026-10-12 10:00:00');

        // The address is taken: the registry says so to the dry run as it would to the call.
        $this->agent('events_create', ['title' => 'Class', 'dry_run' => true])->assertHasErrors(['slug']);

        $dry = $this->content($this->agent('events_create', ['title' => 'Workshop', 'values' => ['lead' => 'Hands on.'], 'dry_run' => true]));
        $this->assertSame(['en' => '/events/workshop'], $dry['would_answer_at']);
        $this->assertSame(['en' => 'Hands on.'], $dry['values']['lead']);
        $this->assertSame(1, Event::withTrashed()->count());
        $this->assertSame(0, Route::query()->where('path', 'events/workshop')->count());

        $this->agent('events_update', [
            'event' => $event->id,
            'values' => ['ends_at' => '2026-10-11T10:00:00+00:00'],
            'dry_run' => true,
        ])->assertHasErrors(['ends_at']);

        $this->agent('events_update', ['event' => $event->id, 'values' => ['lead' => 'New.'], 'dry_run' => true])->assertOk();
        $this->assertFalse($event->refresh()->hasDraft());

        $this->agent('events_publish', ['event' => $event->id, 'dry_run' => true])->assertOk();
        $this->assertSame(1, $event->versions()->where('kind', EntityVersion::KIND_PUBLISHED)->count());
    }

    #[Test]
    public function what_is_not_on_the_site_cannot_be_taken_off_it_and_the_bin_takes_nothing_twice(): void
    {
        $draft = $this->event('draft', '2026-10-12 10:00:00', published: false);

        $this->agent('events_unpublish', ['event' => $draft->id, 'dry_run' => true])->assertHasErrors(['not on the site']);
        $this->agent('events_unpublish', ['event' => $draft->id])->assertHasErrors(['not on the site']);

        $draft->delete();

        $this->agent('events_delete', ['event' => $draft->id, 'dry_run' => true])->assertHasErrors(['already in the bin']);
        $this->agent('events_delete', ['event' => $draft->id])->assertHasErrors(['already in the bin']);
    }

    #[Test]
    public function an_event_comes_back_out_of_the_bin_or_goes_for_good(): void
    {
        $event = $this->event('class', '2026-10-12 10:00:00');

        $this->agent('events_restore', ['event' => $event->id])->assertHasErrors(['not in the bin']);
        $this->agent('events_purge', ['event' => $event->id])->assertHasErrors(['not in the bin']);

        $this->agent('events_delete', ['event' => $event->id])->assertOk();

        $dry = $this->content($this->agent('events_restore', ['event' => $event->id, 'dry_run' => true]));
        $this->assertSame('/events/class', $dry['would_answer_at']['en']['path']);
        $this->assertTrue($event->refresh()->trashed());

        $restored = $this->content($this->agent('events_restore', ['event' => $event->id]));
        $this->assertSame('/events/class', $restored['event']['urls']['en']['path']);
        $this->assertFalse($event->refresh()->trashed());

        $this->agent('events_delete', ['event' => $event->id])->assertOk();
        $this->agent('events_purge', ['event' => $event->id, 'dry_run' => true])->assertOk();
        $this->assertNotNull(Event::withTrashed()->find($event->id));

        $this->assertSame(['purged' => $event->id], $this->content($this->agent('events_purge', ['event' => $event->id])));
        $this->assertNull(Event::withTrashed()->find($event->id));
        $this->assertSame(0, Route::query()->where('entity_type', $event->getMorphClass())->where('entity_id', $event->id)->count());
        $this->assertSame(0, EntityVersion::query()->where('entity_id', $event->id)->count());
    }

    #[Test]
    public function a_bare_string_from_the_panel_is_written_in_the_content_language(): void
    {
        // The interface in German over a site published in English alone.
        $this->app->setLocale('de');
        $event = $this->event('class', '2026-10-12 10:00:00');

        $this->app->make(EventWriter::class)->save($event, ['lead' => 'Three dishes.']);

        $this->assertSame(['en' => 'Three dishes.'], $event->refresh()->draftValues()['lead']);
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
