<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Editing\RecordEvents;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Pages\Models\Page;

/**
 * An editor in the panel and an agent over MCP on the same page.
 *
 * The case this was written after: the agent set the Hero's eyebrow, the editor — still on the
 * revision before it — saved a new heading, was refused, pressed «Keep mine», and the eyebrow was
 * gone with no way back. The panel now merges such a save; what is pinned here is the server's
 * half: the agent has to read before it writes, it hears who has the page open, and a draft
 * written over by somebody else is kept.
 */
final class ConcurrentEditingTest extends TestCase
{
    #[Test]
    public function an_agent_writes_what_it_read_or_says_it_means_to_overwrite(): void
    {
        $this->page('about');
        $agent = $this->editor();

        $this->agent('pages_update', ['page' => '/about', 'values' => ['title' => ['en' => 'About us']]], $agent)
            ->assertHasErrors(['Read the page first', 'pages_get', 'being_edited_by', 'force: true']);

        // A rehearsal writes nothing and needs nothing.
        $this->agent('pages_update', ['page' => '/about', 'values' => ['title' => ['en' => 'About us']], 'dry_run' => true], $agent)
            ->assertOk();

        $this->agent('pages_update', ['page' => '/about', 'values' => ['title' => ['en' => 'About us']], 'force' => true], $agent)
            ->assertOk();

        $revision = $this->content($this->agent('pages_get', ['page' => '/about'], $agent))['revision'];

        $this->agent('pages_update', ['page' => '/about', 'values' => ['title' => ['en' => 'About']], 'revision' => $revision], $agent)
            ->assertOk();
    }

    #[Test]
    public function an_agent_hears_who_has_the_page_open_in_the_panel(): void
    {
        $about = $this->page('about');
        $anna = $this->named('Anna');

        $this->assertSame([], $this->content($this->agent('pages_get', ['page' => '/about'], $this->editor()))['being_edited_by']);

        // The editor's heartbeat: it says "I am here" and hears what it cannot see.
        $this->actingAs($anna, 'cms')
            ->postJson('/api/cms/editing/pages/'.$about->getKey())
            ->assertOk()
            ->assertJsonPath('data.revision', $this->content($this->agent('pages_get', ['page' => '/about'], $this->editor()))['revision'])
            ->assertJsonPath('data.editors', []);

        $present = $this->content($this->agent('pages_get', ['page' => '/about'], $this->editor()))['being_edited_by'];

        $this->assertCount(1, $present);
        $this->assertSame('Anna', $present[0]['name']);

        // The content tools say the same: the blocks are where an agent usually writes.
        $content = $this->content($this->agent('blocks_get_content', ['entity' => 'page', 'id' => $about->getKey(), 'outline' => true], $this->editor(['pages.view', 'blocks.view'])));

        $this->assertSame(['Anna'], array_column($content['being_edited_by'], 'name'));

        // Closing the editor says so at once.
        $this->actingAs($anna, 'cms')->deleteJson('/api/cms/editing/pages/'.$about->getKey())->assertNoContent();

        $this->assertSame([], $this->content($this->agent('pages_get', ['page' => '/about'], $this->editor()))['being_edited_by']);
    }

    #[Test]
    public function the_heartbeat_names_the_last_change_and_its_door(): void
    {
        $about = $this->page('about');
        $agent = $this->named('Administrator');

        $revision = $this->content($this->agent('pages_get', ['page' => '/about'], $agent))['revision'];
        $this->agent('pages_update', ['page' => '/about', 'values' => ['title' => ['en' => 'About us']], 'revision' => $revision], $agent)->assertOk();

        $this->actingAs($this->editor(), 'cms')
            ->postJson('/api/cms/editing/pages/'.$about->getKey())
            ->assertOk()
            ->assertJsonPath('data.changed.author', 'Administrator')
            ->assertJsonPath('data.changed.source', 'mcp');

        // A stale save in the panel is refused with the same: who, and through which door.
        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($about->getKey()), ['values' => ['title' => ['en' => 'Mine']], 'revision' => $revision])
            ->assertStatus(409)
            ->assertJsonPath('changed.source', 'mcp')
            ->assertJsonPath('data.values.title.en', 'About us');
    }

    #[Test]
    public function a_draft_written_over_by_somebody_else_is_kept_and_comes_back(): void
    {
        $about = $this->page('about');
        $agent = $this->named('Administrator');
        $editor = $this->named('Anna');

        // The agent writes the draft…
        $revision = $this->content($this->agent('pages_get', ['page' => '/about'], $agent))['revision'];
        $this->agent('pages_update', ['page' => '/about', 'values' => ['title' => ['en' => 'Agent title']], 'revision' => $revision], $agent)->assertOk();

        // …and the editor saves over it without a revision, the way an import would.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api($about->getKey()), ['values' => ['title' => ['en' => 'Editor title']]])
            ->assertOk();

        $overwritten = $about->versions()->where('kind', EntityVersion::KIND_OVERWRITTEN)->get();

        $this->assertCount(1, $overwritten);
        $this->assertSame('Agent title', $overwritten[0]->payload['title']['en']);
        $this->assertSame(EntityVersion::SOURCE_MCP, $overwritten[0]->source);

        // The editor's own next keystroke is not somebody else's draft: nothing more is kept.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api($about->getKey()), ['values' => ['title' => ['en' => 'Editor title, again']]])
            ->assertOk();

        $this->assertSame(1, $about->versions()->where('kind', EntityVersion::KIND_OVERWRITTEN)->count());

        // The agent finds it among the drafts and puts it back.
        $drafts = $this->content($this->agent('pages_versions', ['page' => '/about'], $agent))['drafts'];
        $kept = array_values(array_filter($drafts, static fn (array $draft): bool => $draft['kind'] === EntityVersion::KIND_OVERWRITTEN));

        $this->assertSame('Administrator', $kept[0]['author']);
        $this->assertSame('mcp', $kept[0]['source']);

        $this->agent('pages_version_restore', ['page' => '/about', 'draft' => $kept[0]['draft']], $agent)->assertOk();

        $this->assertSame('Agent title', $about->refresh()->draftValues()['title']['en']);

        // The panel lists the same copies and restores them too.
        $this->actingAs($editor, 'cms')
            ->getJson('/api/cms/editing/pages/'.$about->getKey().'/drafts')
            ->assertOk()
            ->assertJsonPath('data.0.source', fn (string $source): bool => in_array($source, ['panel', 'mcp'], true));

        $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/editing/pages/'.$about->getKey().'/drafts/'.$kept[0]['draft'].'/restore')
            ->assertOk()
            ->assertJsonPath('data.restored', $kept[0]['draft']);
    }

    #[Test]
    public function a_publication_keeps_the_drafts_it_may_have_lost(): void
    {
        $about = $this->page('about');
        $agent = $this->named('Administrator');

        $about->saveDraft(['title' => ['en' => 'Agent title']], (int) $agent->getKey(), EntityVersion::SOURCE_MCP);
        $about->saveDraft(['title' => ['en' => 'Editor title']], (int) $this->editor()->getKey(), EntityVersion::SOURCE_PANEL);
        $about->publish();

        $this->assertSame(0, $about->versions()->autosaves()->count());
        $this->assertSame(1, $about->versions()->where('kind', EntityVersion::KIND_OVERWRITTEN)->count());
    }

    #[Test]
    public function only_a_reader_of_pages_hears_the_heartbeat_and_only_a_writer_restores(): void
    {
        $about = $this->page('about');

        $this->actingAs($this->editor([]), 'cms')
            ->postJson('/api/cms/editing/pages/'.$about->getKey())
            ->assertForbidden();

        $this->actingAs($this->editor(['pages.view']), 'cms')
            ->postJson('/api/cms/editing/pages/'.$about->getKey().'/drafts/1/restore')
            ->assertForbidden();

        $this->actingAs($this->editor(), 'cms')
            ->postJson('/api/cms/editing/nothing/1')
            ->assertNotFound();
    }

    #[Test]
    public function what_changes_a_page_takes_the_revision_while_somebody_has_it_open(): void
    {
        $about = $this->page('about');
        $about->saveDraft(['title' => ['en' => 'About, edited'], 'slug' => ['en' => 'about'], 'blocks' => []]);
        $this->page('contact');
        $agent = $this->named('Agent');

        // Nobody in the panel: a script may publish without reading first, as before.
        $this->agent('pages_publish', ['page' => '/about', 'dry_run' => true], $agent)->assertOk();

        $this->actingAs($this->named('Owner'), 'cms')->postJson('/api/cms/editing/pages/'.$about->getKey())->assertOk();

        foreach ([
            ['pages_publish', []],
            ['pages_unpublish', []],
            ['pages_discard', []],
            ['pages_delete', []],
            ['pages_move', ['target' => '/contact', 'zone' => 'inside']],
            ['pages_version_restore', ['number' => 1]],
        ] as [$tool, $extra]) {
            $this->agent($tool, ['page' => $about->getKey()] + $extra, $agent)
                ->assertHasErrors(['Owner has this page open in the panel', 'pages_get', 'force: true']);

            $this->agent($tool, ['page' => $about->getKey(), 'revision' => 'stale'] + $extra, $agent)
                ->assertHasErrors(['changed since you read it']);
        }

        $revision = $this->content($this->agent('pages_get', ['page' => '/about'], $agent))['revision'];

        $this->agent('pages_publish', ['page' => '/about', 'revision' => $revision], $agent)->assertOk();
        $this->assertSame('About, edited', $about->refresh()->getTranslation('title', 'en'));
    }

    #[Test]
    public function a_publication_from_the_panel_carrying_a_stale_revision_is_refused(): void
    {
        $about = $this->page('about');
        $about->saveDraft(['title' => ['en' => 'Seen'], 'slug' => ['en' => 'about'], 'blocks' => []]);
        $owner = $this->named('Owner');

        $held = $this->actingAs($owner, 'cms')->getJson($this->api($about->getKey()))->json('data.revision');

        // Somebody else writes after the editor read the page.
        $this->agent('pages_update', ['page' => '/about', 'values' => ['title' => ['en' => 'Unseen']], 'force' => true], $this->named('Administrator'));

        $this->actingAs($owner, 'cms')
            ->postJson($this->api($about->getKey()).'/publish', ['revision' => $held])
            ->assertStatus(409)
            ->assertJsonPath('changed.author', 'Administrator')
            ->assertJsonStructure(['message', 'revision']);

        $this->assertNotSame('Unseen', $about->refresh()->getTranslation('title', 'en'));

        $fresh = $this->actingAs($owner, 'cms')->getJson($this->api($about->getKey()))->json('data.revision');

        $this->actingAs($owner, 'cms')
            ->postJson($this->api($about->getKey()).'/publish', ['revision' => $fresh])
            ->assertOk();

        $this->assertSame('Unseen', $about->refresh()->getTranslation('title', 'en'));
    }

    #[Test]
    public function the_heartbeat_says_what_the_revision_does_not(): void
    {
        $about = $this->page('about');
        $about->saveDraft(['title' => ['en' => 'About, edited'], 'slug' => ['en' => 'about'], 'blocks' => []]);
        $contact = $this->page('contact');
        $owner = $this->named('Owner');
        $agent = $this->named('Agent');
        $url = '/api/cms/editing/pages/'.$about->getKey();

        $first = $this->actingAs($owner, 'cms')->postJson($url)->assertOk();
        $first->assertJsonPath('data.state.status', 'modified')
            ->assertJsonPath('data.state.has_draft', true)
            ->assertJsonPath('data.state.trashed', false)
            ->assertJsonPath('data.place.paths.en', '/about');
        $revision = $first->json('data.revision');

        // Published by somebody else: the content and so the revision are the same, the state is not.
        $this->agent('pages_publish', ['page' => '/about', 'revision' => $revision], $agent)->assertOk();

        $after = $this->actingAs($owner, 'cms')->postJson($url)->assertOk();
        $after->assertJsonPath('data.revision', $revision)
            ->assertJsonPath('data.state.status', 'published')
            ->assertJsonPath('data.state.has_draft', false);
        $this->assertSame(['kind' => 'published', 'source' => 'mcp'], $this->last($after->json('data.events'), ['kind', 'source']));

        // Moved under another page: the place and the address follow, and the move is an event.
        $this->agent('pages_move', ['page' => '/about', 'target' => '/contact', 'zone' => 'inside', 'revision' => $revision], $agent)->assertOk();

        $moved = $this->actingAs($owner, 'cms')->postJson($url)->assertOk();
        $moved->assertJsonPath('data.place.parent_id', $contact->getKey())
            ->assertJsonPath('data.place.paths.en', '/contact/about');
        $this->assertSame(['kind' => 'moved'], $this->last($moved->json('data.events'), ['kind']));

        // In the bin: still answered, and said so.
        $this->agent('pages_delete', ['page' => $about->getKey(), 'revision' => $revision], $agent)->assertOk();

        $binned = $this->actingAs($owner, 'cms')->postJson($url)->assertOk();
        $binned->assertJsonPath('data.state.trashed', true)
            ->assertJsonPath('data.state.status', 'trashed');
        $this->assertSame(['kind' => 'trashed', 'author' => 'Agent'], $this->last($binned->json('data.events'), ['kind', 'author']));

        // A save into the bin is not a save: the editor hears 404 and asks the heartbeat why.
        $this->actingAs($owner, 'cms')
            ->putJson($this->api($about->getKey()), ['values' => ['title' => ['en' => 'Too late']]])
            ->assertNotFound();
    }

    #[Test]
    public function a_page_deleted_for_good_is_gone_rather_than_missing(): void
    {
        $about = $this->page('about');
        $owner = $this->named('Owner');
        $agent = $this->named('Agent');
        $url = '/api/cms/editing/pages/'.$about->getKey();

        $revision = $this->actingAs($owner, 'cms')->postJson($url)->assertOk()->json('data.revision');

        $this->agent('pages_delete', ['page' => $about->getKey(), 'revision' => $revision], $agent)->assertOk();
        $this->agent('pages_purge', ['page' => $about->getKey()], $agent)->assertOk();

        // The heartbeat: 410 with who and when, not the 404 a typo in an id would get.
        $gone = $this->actingAs($owner, 'cms')->postJson($url)->assertStatus(410);
        $gone->assertJsonPath('gone.kind', 'purged')
            ->assertJsonPath('gone.author', 'Agent')
            ->assertJsonPath('gone.source', 'mcp');
        $this->assertStringContainsString('Agent, through an agent deleted this for good at', (string) $gone->json('message'));

        // A save and a publication answer the same, through the module's own endpoints.
        $this->actingAs($owner, 'cms')
            ->putJson($this->api($about->getKey()), ['values' => ['title' => ['en' => 'Too late']]])
            ->assertStatus(410)
            ->assertJsonPath('gone.author', 'Agent');
        $this->actingAs($owner, 'cms')
            ->postJson($this->api($about->getKey()).'/publish', ['revision' => $revision])
            ->assertStatus(410);

        // A page that never was is still a 404, and so is one only in the bin.
        $this->actingAs($owner, 'cms')->postJson('/api/cms/editing/pages/99999')->assertNotFound();
        $this->actingAs($owner, 'cms')->putJson($this->api(99999), ['values' => []])->assertNotFound();
    }

    #[Test]
    public function the_purge_of_a_page_in_the_bin_is_not_heard_as_a_second_trip_there(): void
    {
        $about = $this->page('about');
        $id = $about->getKey();
        $about->delete();
        Page::withTrashed()->findOrFail($id)->forceDelete();

        $events = $this->app->make(RecordEvents::class);

        $this->assertSame('purged', $events->purged(Page::class, $id)['kind'] ?? null);
        $this->assertSame(['trashed', 'purged'], array_slice(array_column($events->of($about), 'kind'), -2));
    }

    #[Test]
    public function a_dry_run_leaves_no_event_behind(): void
    {
        $about = $this->page('about');
        $owner = $this->named('Owner');

        $this->agent('pages_delete', ['page' => '/about', 'dry_run' => true], $this->named('Agent'))->assertOk();

        $events = $this->actingAs($owner, 'cms')->postJson('/api/cms/editing/pages/'.$about->getKey())->json('data.events');

        $this->assertNotContains('trashed', array_column($events, 'kind'));
    }

    #[Test]
    public function a_version_put_back_is_an_event_with_its_number(): void
    {
        $about = $this->page('about');
        $about->saveDraft(['title' => ['en' => 'Second'], 'slug' => ['en' => 'about'], 'blocks' => []]);
        $about->publish();

        $this->agent('pages_version_restore', ['page' => '/about', 'number' => 1], $this->named('Agent'))->assertOk();

        $events = $this->actingAs($this->named('Owner'), 'cms')->postJson('/api/cms/editing/pages/'.$about->getKey())->json('data.events');

        $this->assertSame(['kind' => 'restored_version', 'detail' => ['number' => 1]], $this->last($events, ['kind', 'detail']));
    }

    #[Test]
    public function a_stale_place_in_a_save_does_not_move_the_page_back(): void
    {
        $about = $this->page('about');
        $contact = $this->page('contact');
        $home = $about->parent_id;
        $owner = $this->named('Owner');

        $held = $this->actingAs($owner, 'cms')->getJson($this->api($about->getKey()))->json('data');

        // Moved by somebody else while the form was open…
        $this->agent('pages_move', ['page' => '/about', 'target' => '/contact', 'zone' => 'inside'], $this->named('Agent'))->assertOk();

        // …and the stale form saved, with where it thought the page was.
        $this->actingAs($owner, 'cms')
            ->putJson($this->api($about->getKey()), [
                'values' => ['title' => ['en' => 'About, saved late']] + ['parent_id' => $home],
                'parent_id' => $home,
                'revision' => $held['revision'],
            ])
            ->assertOk();

        $this->assertSame($contact->getKey(), $about->refresh()->parent_id);

        // Nor does publishing that draft.
        $about->publish();
        $this->assertSame($contact->getKey(), $about->fresh()?->parent_id);
    }

    #[Test]
    public function each_copy_of_the_draft_names_what_it_changed(): void
    {
        $about = $this->page('about');
        $agent = $this->named('Agent');
        $editor = $this->named('Anna');

        $revision = $this->content($this->agent('pages_get', ['page' => '/about'], $agent))['revision'];
        $this->agent('pages_update', ['page' => '/about', 'values' => ['title' => ['en' => 'Agent title']], 'revision' => $revision], $agent)->assertOk();

        $this->actingAs($editor, 'cms')
            ->putJson($this->api($about->getKey()), ['values' => ['title' => ['en' => 'Agent title'], 'slug' => ['en' => 'about-us']]])
            ->assertOk();

        $drafts = $this->actingAs($editor, 'cms')->getJson('/api/cms/editing/pages/'.$about->getKey().'/drafts')->assertOk()->json('data');

        // Newest first: Anna's copy changed the address, the agent's the title — not both, each.
        $this->assertSame([[['field' => 'slug'], ['field' => 'en']]], $drafts[0]['paths']);
        $this->assertContains([['field' => 'title'], ['field' => 'en']], $drafts[1]['paths']);
        $this->assertNotContains([['field' => 'slug'], ['field' => 'en']], $drafts[1]['paths']);
    }

    #[Test]
    public function a_page_has_one_revision_whichever_tool_reads_it(): void
    {
        $about = $this->page('about');
        $agent = $this->editor(['pages.view', 'pages.manage', 'blocks.view', 'blocks.manage']);

        $fromPages = $this->content($this->agent('pages_get', ['page' => '/about'], $agent))['revision'];
        $fromBlocks = $this->content($this->agent('blocks_get_content', ['entity' => 'page', 'id' => $about->getKey()], $agent))['revision'];

        $this->assertSame($fromPages, $fromBlocks);

        // Read with one, written with the other, and back.
        $written = $this->content($this->agent('pages_update', ['page' => '/about', 'values' => ['title' => ['en' => 'About us']], 'revision' => $fromBlocks], $agent));

        $this->agent('blocks_set_content', ['entity' => 'page', 'id' => $about->getKey(), 'blocks' => [], 'revision' => $written['revision']], $agent)->assertOk();
    }

    /**
     * The newest event, cut to the keys asked for.
     *
     * @param  list<array<string, mixed>>  $events
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function last(array $events, array $keys): array
    {
        $event = end($events);

        return is_array($event) ? array_intersect_key($event, array_flip($keys)) : [];
    }

    private function named(string $name): CmsUser
    {
        $user = $this->editor();
        $user->forceFill(['name' => $name])->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments, CmsUser $as): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as, 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertOk()->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
