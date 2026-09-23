<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;

/**
 * The section's API (§10): the menus, the tree of one of them, and what an editor may do to it.
 */
class PanelTest extends TestCase
{
    public function test_the_section_is_in_the_manifest_with_its_permissions(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk();

        $modules = $response->json('data.modules');
        $module = null;

        foreach (is_array($modules) ? $modules : [] as $candidate) {
            if (($candidate['id'] ?? null) === 'menu') {
                $module = $candidate;
            }
        }

        $this->assertNotNull($module);
        $this->assertSame(['menu.view', 'menu.manage'], $module['permissions']);
    }

    public function test_a_stranger_and_a_viewer_are_kept_out_of_writing(): void
    {
        $this->postJson($this->api(), ['key' => 'aside', 'title' => 'Aside'])->assertUnauthorized();

        $this->actingAs($this->editor(['menu.view']), 'cms')
            ->postJson($this->api(), ['key' => 'aside', 'title' => 'Aside'])
            ->assertForbidden();

        $this->actingAs($this->editor(['menu.view']), 'cms')->getJson($this->api())->assertOk();
    }

    public function test_the_list_holds_the_declared_menus_before_any_of_them_has_a_row(): void
    {
        $this->assertSame(0, Menu::query()->count());

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertSame(['header', 'footer'], array_column((array) $response->json('data'), 'key'));

        $header = $response->json('data.0');

        $this->assertNull($header['id'], 'A declared menu has no row until it is saved.');
        $this->assertSame('Header', $header['title']);
        $this->assertTrue($header['declared']);
        $this->assertSame(0, $header['items_count']);
        $this->assertSame(['link', 'button'], $header['variants']);
        $this->assertFalse($header['can']['delete']);
        $this->assertFalse($header['can']['rename']);
    }

    public function test_a_menu_of_ones_own_is_made_deleted_and_counted(): void
    {
        $editor = $this->editor();

        $made = $this->actingAs($editor, 'cms')
            ->postJson($this->api(), ['key' => 'aside', 'title' => 'Aside'])
            ->assertCreated();

        $this->assertSame('aside', $made->json('data.key'));
        $this->assertFalse($made->json('data.declared'));
        $this->assertTrue($made->json('data.can.delete'));

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('aside/items'), [
                'link' => ['target' => 'url', 'url' => '/aside'],
                'title' => ['en' => 'Aside'],
            ])
            ->assertCreated();

        $listed = $this->actingAs($editor, 'cms')->getJson($this->api())->assertOk()->json('data');
        $aside = array_values(array_filter((array) $listed, static fn (array $row): bool => $row['key'] === 'aside'));

        $this->assertSame(1, $aside[0]['items_count']);

        $this->actingAs($editor, 'cms')->deleteJson($this->api('aside'))->assertNoContent();

        $this->assertSame(0, Menu::query()->where('key', 'aside')->count());
        $this->assertSame(0, MenuItem::query()->count(), 'The items go with the menu.');
    }

    public function test_a_declared_menu_cannot_be_deleted_and_its_row_appears_on_the_first_save(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')
            ->patchJson($this->api('header'), ['title' => 'Top bar'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Top bar');

        $this->assertSame(1, Menu::query()->where('key', 'header')->count());

        // The model refuses, which is where the rule belongs: an agent and an import come
        // through the same door.
        $this->actingAs($editor, 'cms')->deleteJson($this->api('header'))->assertStatus(422);

        $this->assertSame(1, Menu::query()->where('key', 'header')->count());
    }

    public function test_a_menu_that_is_neither_declared_nor_made_is_not_found(): void
    {
        $this->actingAs($this->editor(), 'cms')->getJson($this->api('nowhere/items'))->assertNotFound();
    }

    public function test_the_tree_comes_back_nested_with_the_target_resolved(): void
    {
        $thing = $this->thing('about', 'About');
        $menu = $this->menu('header');

        $services = $this->item(['title' => ['en' => 'Services'], 'is_heading' => true], $menu);
        $this->item(
            ['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $thing->getKey()],
            $menu,
            $services,
        );

        $tree = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('header/items'))
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $tree);
        $this->assertSame('Services', $tree[0]['label']);
        $this->assertTrue($tree[0]['is_heading']);

        $child = $tree[0]['children'][0];

        // No label of its own: the row is called what the entity is called, and the address is
        // worked out here rather than asked for by the browser.
        $this->assertSame('About', $child['label']);
        $this->assertStringEndsWith('/about', (string) $child['href']);
        $this->assertSame('About', $child['resolved']['title']);
        $this->assertTrue($child['available']);
    }

    public function test_a_draft_target_is_offered_and_marked_rather_than_left_out(): void
    {
        $thing = $this->thing('draft', 'Draft', published: false);

        $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $thing->getKey()]);

        $tree = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('header/items'))
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $tree, 'The panel shows what the site leaves out.');
        $this->assertFalse($tree[0]['available']);
    }

    public function test_an_item_is_written_through_the_same_link_value_every_field_uses(): void
    {
        $editor = $this->editor();

        $made = $this->actingAs($editor, 'cms')
            ->postJson($this->api('header/items'), [
                'title' => ['en' => 'Speak to Katia'],
                // The anchor arrives inside the address, the way it is pasted out of a browser.
                'link' => [
                    'target' => 'url',
                    'url' => 'https://example.com/book#slot',
                    'new_tab' => true,
                    'rel' => ['sponsored', 'nonsense'],
                ],
                'variant' => 'button',
                'visible' => true,
            ])
            ->assertCreated();

        $this->assertSame('slot', $made->json('data.hash'));
        $this->assertSame('https://example.com/book', $made->json('data.url'));
        $this->assertSame('https://example.com/book#slot', $made->json('data.href'));
        $this->assertSame(['sponsored'], $made->json('data.rel'), 'A rel nobody recognises is dropped.');
        $this->assertSame('button', $made->json('data.variant'));
    }

    public function test_a_look_the_menu_does_not_offer_is_refused(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('footer/items'), [
                'link' => ['target' => 'none'],
                // `button` belongs to the header; the footer declares none and gets the fallback.
                'variant' => 'button',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('variant');
    }

    public function test_an_item_is_deleted_with_what_is_under_it_and_says_how_many(): void
    {
        $menu = $this->menu('header');

        $services = $this->item(['title' => ['en' => 'Services']], $menu);
        $design = $this->item(['title' => ['en' => 'Design']], $menu, $services);
        $this->item(['title' => ['en' => 'Print']], $menu, $design);

        $this->actingAs($this->editor(), 'cms')
            ->deleteJson($this->api("header/items/{$services->getKey()}"))
            ->assertOk()
            ->assertJsonPath('data.deleted', 3);

        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_an_item_of_another_menu_is_not_found_under_this_one(): void
    {
        $stray = $this->item(['title' => ['en' => 'Terms']], $this->menu('footer'));

        $this->actingAs($this->editor(), 'cms')
            ->patchJson($this->api("header/items/{$stray->getKey()}"), ['link' => ['target' => 'none']])
            ->assertNotFound();
    }
}
