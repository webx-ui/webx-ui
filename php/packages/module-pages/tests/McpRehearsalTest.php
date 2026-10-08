<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;

/**
 * The agent's door, where a full pass over the tools found it disagreeing with itself: a dry run
 * that promised what the call then refused, an answer that looked like a change that never
 * happened, a page that came back where it had no business being.
 */
final class McpRehearsalTest extends TestCase
{
    #[Test]
    public function publishing_a_draft_onto_a_taken_address_is_a_clean_refusal_rehearsal_included(): void
    {
        $parent = $this->page('parent');
        $first = $this->page('child-1', $parent, published: false);
        $this->page('child-2', $parent);

        $this->agent('update', ['page' => $first->getKey(), 'values' => ['slug' => ['en' => 'child-2']], 'force' => true])->assertOk();

        // The rehearsal publishes for real and rolls back, so the registry is asked as it will be.
        $this->agent('publish', ['page' => $first->getKey(), 'dry_run' => true])->assertHasErrors(['already taken']);

        // And the call itself: the address refused, not "Unknown column children_count".
        $this->agent('publish', ['page' => $first->getKey()])->assertHasErrors(['already taken']);

        $first->refresh();
        $this->assertNull($first->published_at);
        $this->assertSame('child-1', $first->getTranslation('slug', 'en'));
    }

    #[Test]
    public function a_page_with_nothing_waiting_says_so_instead_of_pretending_to_publish(): void
    {
        $page = $this->page('about');

        $dry = $this->content($this->agent('publish', ['page' => '/about', 'dry_run' => true])->assertOk());
        $this->assertTrue($dry['nothing_to_publish']);
        $this->assertNull($dry['would_publish']);

        $this->assertTrue($this->content($this->agent('publish', ['page' => '/about'])->assertOk())['nothing_to_publish']);
        $this->assertCount(1, $page->publishedVersions()->get());
    }

    #[Test]
    public function a_page_under_a_parent_in_the_bin_is_not_restored_and_the_rehearsal_says_so(): void
    {
        $parent = $this->page('parent');
        $child = $this->page('child', $parent);
        $grandchild = $this->page('grandchild', $child);

        $this->agent('delete', ['page' => $grandchild->getKey()])->assertOk();
        $this->agent('delete', ['page' => $parent->getKey()])->assertOk();

        $this->agent('restore', ['page' => $grandchild->getKey(), 'dry_run' => true])->assertHasErrors(['in the bin']);
        $this->agent('restore', ['page' => $grandchild->getKey()])->assertHasErrors(['#'.$parent->getKey()]);

        $this->assertTrue(Page::withTrashed()->findOrFail($grandchild->getKey())->trashed());
        $this->assertFalse(Route::query()->where('path', 'grandchild')->exists());
    }

    #[Test]
    public function a_restore_onto_a_taken_address_is_refused_by_the_rehearsal_too(): void
    {
        $about = $this->page('about');
        $this->agent('delete', ['page' => $about->getKey()])->assertOk();
        $this->page('about');

        $this->agent('restore', ['page' => $about->getKey(), 'dry_run' => true])->assertHasErrors(['already taken']);
        $this->assertTrue(Page::withTrashed()->findOrFail($about->getKey())->trashed());
    }

    #[Test]
    public function an_empty_title_is_refused(): void
    {
        $page = $this->page('about');

        $this->agent('update', ['page' => '/about', 'values' => ['title' => ['en' => '']], 'force' => true])->assertHasErrors(['title']);
        $this->agent('update', ['page' => '/about', 'values' => ['title' => ['en' => '']], 'dry_run' => true])->assertHasErrors(['title']);

        $this->assertFalse($page->refresh()->hasDraft());
    }

    #[Test]
    public function a_page_created_without_values_records_its_author_like_one_created_with_them(): void
    {
        $agent = $this->editor();

        $bare = $this->content($this->agent('create', ['title' => 'Bare'], $agent)->assertOk())['page'];
        $full = $this->content($this->agent('create', ['title' => 'Full', 'values' => ['title' => ['en' => 'Full']]], $agent)->assertOk())['page'];

        foreach ([$bare, $full] as $page) {
            $this->assertSame('Editor', $page['edited_by']);
            $this->assertTrue($page['has_draft']);
        }
    }

    #[Test]
    public function a_reorder_among_siblings_reports_no_address_changed(): void
    {
        $first = $this->page('first');
        $second = $this->page('second');

        $dry = $this->content($this->agent('move', ['page' => $second->getKey(), 'target' => $first->getKey(), 'zone' => 'before', 'dry_run' => true])->assertOk());
        $this->assertSame(0, $dry['addresses_would_change']);

        $moved = $this->content($this->agent('move', ['page' => $second->getKey(), 'target' => $first->getKey(), 'zone' => 'before'])->assertOk());
        $this->assertSame(0, $moved['addresses_changed']);

        // Into another parent is still the page and its branch.
        $this->page('below', $second);
        $this->assertSame(2, $this->content($this->agent('move', ['page' => $second->getKey(), 'target' => $first->getKey(), 'zone' => 'inside'])->assertOk())['addresses_changed']);

        $this->agent('move', ['page' => $first->getKey(), 'target' => $first->getKey(), 'zone' => 'after'])
            ->assertHasErrors([(string) __('webx-pages::errors.move-beside-self')]);
    }

    #[Test]
    public function a_trashed_page_in_the_tree_says_where_it_is(): void
    {
        $about = $this->page('about');
        $this->agent('delete', ['page' => $about->getKey()])->assertOk();

        $this->agent('tree', ['trashed' => true])
            ->assertOk()
            ->assertStructuredContent(function (AssertableJson $json): void {
                $row = $json->etc()->toArray()['pages'][0];

                $this->assertSame('trashed', $row['status']);
                $this->assertSame(['move' => false, 'delete' => false, 'address' => false], $row['can']);
            });

        // `{}`, not `[]`: an empty map is still a map to whoever parses it.
        $this->assertStringContainsString('"urls":{}', json_encode($this->tools()->tool('pages_tree')->tool->handler->__invoke(['trashed' => true]), JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function a_former_address_names_the_page_it_now_leads_to(): void
    {
        $about = $this->page('about');
        $this->agent('update', ['page' => '/about', 'values' => ['slug' => ['en' => 'about-us']], 'force' => true])->assertOk();
        $this->agent('publish', ['page' => $about->getKey()])->assertOk();

        $this->agent('get', ['page' => '/about'])->assertHasErrors(['former address of page #'.$about->getKey(), '/about-us']);
    }

    #[Test]
    public function unpublishing_says_how_many_published_pages_below_stay_on_the_site(): void
    {
        $parent = $this->page('parent');
        $this->page('child', $parent);

        $this->assertSame(1, $this->content($this->agent('unpublish', ['page' => '/parent', 'dry_run' => true])->assertOk())['published_below']);
        $this->assertSame(1, $this->content($this->agent('unpublish', ['page' => '/parent'])->assertOk())['published_below']);
    }

    #[Test]
    public function the_home_page_takes_no_slug_from_an_agent(): void
    {
        $this->agent('update', ['page' => '/', 'values' => ['slug' => ['en' => 'home']]])->assertHasErrors(['no address of its own']);
        $this->agent('update', ['page' => '/', 'values' => ['slug' => ['en' => 'home']], 'dry_run' => true])->assertHasErrors(['no address of its own']);
    }

    #[Test]
    public function the_schemas_say_what_each_field_is(): void
    {
        $create = $this->tools()->tool('pages_create')->tool->inputSchema['properties'];
        $this->assertStringContainsString('The last segment of the address', $create['slug']['description']);
        $this->assertSame(['string', 'object'], $create['slug']['type']);

        // The SEO card is not in a version, and the restore does not promise it.
        $this->assertStringNotContainsString('SEO card and', $this->tools()->tool('pages_version_restore')->tool->description);
    }

    private function tools(): ToolRegistry
    {
        return $this->app->make(ToolRegistry::class);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->tools()->tool('pages_'.$tool));

        return $as instanceof CmsUser
            ? WebxServer::actingAs($as, 'cms')->tool($bound, $arguments)
            : WebxServer::tool($bound, $arguments);
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
