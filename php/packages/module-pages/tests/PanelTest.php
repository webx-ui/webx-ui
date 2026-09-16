<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Pages\Models\Page;

/**
 * The section's API: the level of the tree the list draws, and what an editor may do to a page.
 */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_section_is_in_the_manifest_with_its_permissions(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk();

        $modules = $response->json('data.modules');
        $module = null;

        foreach (is_array($modules) ? $modules : [] as $candidate) {
            if (($candidate['id'] ?? null) === 'pages') {
                $module = $candidate;
            }
        }

        $this->assertNotNull($module);
        $this->assertSame(['pages.view', 'pages.manage'], $module['permissions']);
    }

    #[Test]
    public function a_stranger_and_a_viewer_are_kept_out_of_writing(): void
    {
        $this->postJson($this->api(), ['title' => 'About'])->assertUnauthorized();

        $this->actingAs($this->editor(['pages.view']), 'cms')
            ->postJson($this->api(), ['title' => 'About'])
            ->assertForbidden();

        $this->actingAs($this->editor(['pages.view']), 'cms')
            ->getJson($this->api())
            ->assertOk();
    }

    #[Test]
    public function the_list_pins_the_home_page_beside_the_level_of_its_children(): void
    {
        $about = $this->page('about');
        $this->page('shoes', $this->page('catalog'));

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertTrue($response->json('data.home.is_home'));
        $this->assertSame('', $response->json('data.home.path'));
        $this->assertFalse($response->json('data.home.can.delete'));
        $this->assertFalse($response->json('data.home.can.move'));

        // The level is the home page's children, and the home page is not one of them.
        $this->assertSame(['about', 'catalog'], array_column($response->json('data.items'), 'path'));
        $this->assertSame($about->getKey(), $response->json('data.items.0.id'));

        // Which of them has anything under it — the chevron the table draws before anybody
        // opens a branch.
        $this->assertSame(0, $response->json('data.items.0.children_count'));
        $this->assertSame(1, $response->json('data.items.1.children_count'));
    }

    #[Test]
    public function a_named_parent_answers_with_that_level_alone(): void
    {
        $catalog = $this->page('catalog');
        $this->page('shoes', $catalog);

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api().'?parent='.$catalog->getKey())
            ->assertOk();

        $this->assertNull($response->json('data.home'));
        $this->assertSame(['catalog/shoes'], array_column($response->json('data.items'), 'path'));
    }

    #[Test]
    public function a_flat_list_is_the_whole_tree_in_the_order_it_reads(): void
    {
        $catalog = $this->page('catalog');
        $this->page('shoes', $catalog);
        $this->page('about');

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api().'?flat=1')
            ->assertOk();

        // What a phone asks for: every page at once, the home page simply first, and each row
        // carrying the address that says where it sits (§9, last paragraph).
        $this->assertNull($response->json('data.home'));
        $this->assertSame(
            ['', 'catalog', 'catalog/shoes', 'about'],
            array_column($response->json('data.items'), 'path'),
        );
        $this->assertTrue($response->json('data.items.0.is_home'));

        // The level below a row and the branch under it are different numbers, and a delete
        // takes the second one.
        $this->assertSame(1, $response->json('data.items.1.children_count'));
        $this->assertSame(1, $response->json('data.items.1.descendants_count'));
        $this->assertSame(3, $response->json('data.items.0.descendants_count'));
    }

    #[Test]
    public function searching_answers_with_a_flat_list_of_matches(): void
    {
        $this->page('about');
        $this->page('shoes', $this->page('catalog'));

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api().'?search=sho')
            ->assertOk();

        // A match deep in a branch arrives on its own, with the address that says where it is.
        $this->assertNull($response->json('data.home'));
        $this->assertSame(['catalog/shoes'], array_column($response->json('data.items'), 'path'));

        // The title is searched too, and in the language the panel is open in.
        $this->assertSame(
            ['about'],
            array_column($this->actingAs($this->editor(), 'cms')->getJson($this->api().'?search=Abou')->json('data.items'), 'path'),
        );
    }

    #[Test]
    public function the_status_filter_tells_the_three_states_apart(): void
    {
        $this->page('about');
        $this->page('draft-one', published: false);

        $modified = $this->page('news');
        $modified->saveDraft(['title' => ['en' => 'News, edited']]);

        $editor = $this->editor();

        $states = fn (string $status): array => array_column(
            $this->actingAs($editor, 'cms')->getJson($this->api().'?status='.$status)->json('data.items'),
            'title',
        );

        $this->assertSame(['About'], $states(Page::STATUS_PUBLISHED));
        $this->assertSame(['Draft-one'], $states(Page::STATUS_DRAFT));

        // What the editor is working on is the title the list shows — the draft's, not the
        // one the site is still printing.
        $this->assertSame(['News, edited'], $states(Page::STATUS_MODIFIED));
    }

    #[Test]
    public function a_page_is_created_under_the_home_page_and_refuses_a_taken_address(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor, 'cms')
            ->postJson($this->api(), ['title' => 'About us'])
            ->assertCreated();

        // No slug was given, so it came from the title.
        $this->assertSame('about-us', $response->json('data.path'));
        $this->assertSame(Page::STATUS_DRAFT, $response->json('data.status'));

        $this->actingAs($editor, 'cms')
            ->postJson($this->api(), ['title' => 'About us again', 'slug' => 'about-us'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    #[Test]
    public function a_page_is_duplicated_beside_itself_and_not_onto_the_site(): void
    {
        $about = $this->page('about');

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($about->getKey()).'/duplicate')
            ->assertCreated();

        $this->assertSame('about-copy', $response->json('data.path'));
        $this->assertSame('About (copy)', $response->json('data.title'));
        $this->assertSame(Page::STATUS_DRAFT, $response->json('data.status'));
        $this->assertSame($about->parent_id, $response->json('data.parent_id'));

        // A second copy does not fight the first one for the address.
        $this->assertSame(
            'about-copy-2',
            $this->actingAs($this->editor(), 'cms')->postJson($this->api($about->getKey()).'/duplicate')->json('data.path'),
        );
    }

    #[Test]
    public function publishing_and_unpublishing_move_the_page_on_and_off_the_site(): void
    {
        $page = $this->page('about', published: false);
        $page->saveDraft(['title' => ['en' => 'About us']]);

        $editor = $this->editor();

        $published = $this->actingAs($editor, 'cms')
            ->postJson($this->api($page->getKey()).'/publish')
            ->assertOk();

        $this->assertSame(Page::STATUS_PUBLISHED, $published->json('data.status'));
        $this->assertSame('About us', $published->json('data.title'));
        $this->assertSame('Editor', $published->json('data.edited_by'));

        $this->assertSame(
            Page::STATUS_DRAFT,
            $this->actingAs($editor, 'cms')->postJson($this->api($page->getKey()).'/unpublish')->json('data.status'),
        );

        // The address stays in the registry: whether a page answers is the handler's question,
        // and an address let go here would be taken by the next page of the same name.
        $this->assertSame('about', $page->refresh()->routeCanonical('en')?->path);
    }

    #[Test]
    public function deleting_takes_the_branch_and_restoring_brings_it_back(): void
    {
        $catalog = $this->page('catalog');
        $this->page('shoes', $catalog);

        $editor = $this->editor();

        $this->actingAs($editor, 'cms')
            ->deleteJson($this->api($catalog->getKey()))
            ->assertOk()
            ->assertJsonPath('data.trashed', 2);

        // The bin lists what somebody deleted, not everything that went dark with it.
        $bin = $this->actingAs($editor, 'cms')->getJson($this->api().'?trashed=1')->assertOk();
        $this->assertSame([$catalog->getKey()], array_column($bin->json('data.items'), 'id'));

        $this->actingAs($editor, 'cms')
            ->postJson($this->api($catalog->getKey()).'/restore')
            ->assertOk()
            ->assertJsonPath('data.restored', 2);

        // Back in the tree, at the address it had, with its own branch under it again.
        $this->assertSame(
            ['catalog'],
            array_column($this->actingAs($editor, 'cms')->getJson($this->api())->json('data.items'), 'path'),
        );
        $this->assertSame(
            ['catalog/shoes'],
            array_column($this->actingAs($editor, 'cms')->getJson($this->api().'?parent='.$catalog->getKey())->json('data.items'), 'path'),
        );
    }
}
