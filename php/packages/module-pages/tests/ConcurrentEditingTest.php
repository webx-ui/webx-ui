<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

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
