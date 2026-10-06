<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;

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

        // The title is searched too.
        $this->assertSame(
            ['about'],
            array_column($this->actingAs($this->editor(), 'cms')->getJson($this->api().'?search=Abou')->json('data.items'), 'path'),
        );
    }

    #[Test]
    public function a_page_is_found_by_a_title_in_a_language_the_panel_is_not_open_in(): void
    {
        $this->useLocales('en', 'ru');

        $news = $this->page('news');
        $news->setTranslation('title', 'ru', 'Новости');
        $news->save();

        $editor = $this->editor();

        $paths = fn (string $term): array => array_column(
            $this->actingAs($editor, 'cms')
                ->withHeader('X-Webx-Locale', 'en')
                ->getJson($this->api().'?search='.urlencode($term))
                ->json('data.items'),
            'path',
        );

        // The English panel shows the English title, and finds the page by it.
        $this->assertSame(['news'], $paths('New'));

        // And by the Russian one, which is the point: a page titled in one language only is
        // drawn with that title whatever the panel is open in, so an editor reading it off the
        // screen and typing it into the box has to get it back.
        $this->assertSame(['news'], $paths('Новост'));
    }

    #[Test]
    public function the_bin_is_searched_like_any_other_list(): void
    {
        $editor = $this->editor();
        $about = $this->page('about');
        $catalog = $this->page('catalog');

        foreach ([$about, $catalog] as $page) {
            $this->actingAs($editor, 'cms')->deleteJson($this->api($page->getKey()))->assertOk();
        }

        $ids = function (string $query) use ($editor): array {
            $items = $this->actingAs($editor, 'cms')->getJson($this->api().'?trashed=1'.$query)->json('data.items');

            // Sorted because what the bin is ordered by is when each page was deleted, and two
            // deletes one line apart share a second.
            $found = array_column(is_array($items) ? $items : [], 'id');
            sort($found);

            return $found;
        };

        // The term narrows the bin instead of being dropped on the way in, so a term nothing in
        // there matches answers with nothing rather than with the whole bin.
        $this->assertSame([$catalog->getKey()], $ids('&search=catal'));
        $this->assertSame([], $ids('&search=zzzznothing'));

        // The title is searched as well as the address, and an empty box leaves the bin whole.
        $this->assertSame([$about->getKey()], $ids('&search=Abou'));
        $this->assertSame([$about->getKey(), $catalog->getKey()], $ids(''));
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
    public function a_new_page_is_named_in_the_sites_language_not_the_panels(): void
    {
        // An English-only site, an editor reading the panel in Russian.
        $this->app['config']->set('webx-localization.panel', ['en', 'ru']);

        $response = $this->actingAs($this->editor(), 'cms')
            ->withHeader('X-Webx-Locale', 'ru')
            ->postJson($this->api(), ['title' => 'Alias test'])
            ->assertCreated();

        $page = Page::query()->findOrFail($response->json('data.id'));

        $this->assertSame(['en' => 'Alias test'], $page->getTranslations('title'));
        $this->assertSame(['en' => 'alias-test'], $page->getTranslations('slug'));
        $this->assertSame('alias-test', $response->json('data.path'));
        $this->assertSame('Alias test', $response->json('data.title'));
    }

    #[Test]
    public function a_new_page_is_named_in_the_main_language_even_when_the_panel_speaks_another_of_the_sites(): void
    {
        $this->useLocales('en', 'ru');
        $this->app['config']->set('webx-localization.panel', ['en', 'ru']);

        $response = $this->actingAs($this->editor(), 'cms')
            ->withHeader('X-Webx-Locale', 'ru')
            ->postJson($this->api(), ['title' => 'Alias test'])
            ->assertCreated();

        $page = Page::query()->findOrFail($response->json('data.id'));

        // The main language is the one every page has an address in; the others are the form's.
        $this->assertSame(['en' => 'Alias test'], $page->getTranslations('title'));
    }

    #[Test]
    public function the_editor_is_told_where_publishing_moves_a_renamed_page(): void
    {
        $page = $this->page('alias-test');
        $page->saveDraft(['slug' => ['en' => 'alias-test-2']]);

        $editor = $this->actingAs($this->editor(), 'cms');

        $before = $editor->getJson($this->api($page->getKey()))->assertOk();
        $this->assertSame('alias-test', $before->json('data.page.path'));
        $this->assertSame('alias-test-2', $before->json('data.page.next_path'));

        $published = $editor->postJson($this->api($page->getKey()).'/publish')->assertOk();
        $this->assertSame('alias-test-2', $published->json('data.path'));
        $this->assertNull($published->json('data.next_path'), 'nothing left to move');

        // And a page with nothing renamed in its draft moves nowhere.
        $this->assertNull($editor->getJson($this->api($this->page('about')->getKey()))->json('data.page.next_path'));
    }

    #[Test]
    public function a_restore_brings_back_the_old_addresses_nobody_took(): void
    {
        $page = $this->page('alias-test');

        foreach (['alias-test-2', 'alias-test-3'] as $slug) {
            $page->setTranslation('slug', 'en', $slug);
            $page->save();
        }

        $editor = $this->actingAs($this->editor(), 'cms');
        $editor->deleteJson($this->api($page->getKey()))->assertOk();

        // While it was in the bin, another page took one of its old addresses.
        $other = $this->page('alias-test-2');

        $restored = $editor->postJson($this->api($page->getKey()).'/restore')->assertOk();

        $this->assertSame(['/alias-test'], $restored->json('data.aliases_restored'));
        $this->assertSame(['/alias-test-2'], $restored->json('data.aliases_dropped'));

        $canonical = $page->refresh()->routeCanonical('en');
        $this->assertNotNull($canonical);
        $this->assertSame('alias-test-3', $canonical->path);

        $alias = Route::query()->where('path', 'alias-test')->firstOrFail();
        $this->assertSame(Route::ALIAS, $alias->kind);
        $this->assertSame($canonical->getKey(), $alias->target_id);

        $taken = Route::query()->where('path', 'alias-test-2')->firstOrFail();
        $this->assertSame($other->getKey(), $taken->entity_id);
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

    #[Test]
    public function a_page_in_the_bin_is_counted_under_nothing_on_the_site(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);
        $red = $this->page('red', $shoes);

        $red->delete();

        $rows = array_column(
            $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?flat=1')->assertOk()->json('data.items'),
            null,
            'id',
        );

        // A trashed page keeps its bounds so that a restore can put it back where it was, so
        // `(rgt - lft - 1) / 2` went on counting it as standing under everything above it — and
        // the panel offered to delete a branch of two with one page left in it.
        $this->assertSame(0, $rows[$shoes->getKey()]['descendants_count']);
        $this->assertSame(1, $rows[$catalog->getKey()]['descendants_count']);
        $this->assertSame(2, $rows[$this->home()->getKey()]['descendants_count']);
    }

    #[Test]
    public function a_row_in_the_bin_counts_the_branch_a_restore_would_bring_back(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);
        $red = $this->page('red', $shoes);

        // Deleted first and on its own, so it stays in the bin when the branch above it comes
        // back — and the number the panel says before a restore must not promise it.
        $red->delete();
        $catalog->delete();

        $bin = $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?trashed=1')->assertOk();
        $rows = array_column($bin->json('data.items'), null, 'id');

        $this->assertSame(1, $rows[$catalog->getKey()]['descendants_count'], 'the shoes and not the red ones');
        $this->assertSame(0, $rows[$red->getKey()]['descendants_count']);
        $this->assertSame($shoes->getKey(), $catalog->trashedBranch()->first()?->getKey());
    }
}
