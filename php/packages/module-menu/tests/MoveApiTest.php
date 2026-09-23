<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use WebxUi\Menu\Models\MenuItem;

/**
 * Where an item sits, said as a parent and a position (§10).
 *
 * A parent and an index rather than a sibling and a side, because that is what one drag into one
 * list actually is: the browser knows which list the row landed in and where in it, and asking it
 * to describe that as "after this one" is asking it to throw half of that away and guess it back.
 */
class MoveApiTest extends TestCase
{
    public function test_an_item_moves_inside_its_level(): void
    {
        $menu = $this->menu('header');

        $a = $this->item(['title' => ['en' => 'A']], $menu);
        $this->item(['title' => ['en' => 'B']], $menu);
        $this->item(['title' => ['en' => 'C']], $menu);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("header/items/{$a->getKey()}/move"), ['parent_id' => null, 'index' => 2])
            ->assertOk();

        $this->assertSame(['B', 'C', 'A'], $this->labels('header'));
    }

    public function test_an_item_moves_under_another_and_back_out(): void
    {
        $menu = $this->menu('header');

        $services = $this->item(['title' => ['en' => 'Services']], $menu);
        $design = $this->item(['title' => ['en' => 'Design']], $menu);

        $editor = $this->editor();

        $this->actingAs($editor, 'cms')
            ->postJson($this->api("header/items/{$design->getKey()}/move"), [
                'parent_id' => $services->getKey(),
                'index' => 0,
            ])
            ->assertOk();

        $this->assertSame($services->getKey(), $design->refresh()->parent_id);
        $this->assertSame(1, $design->depth);

        $this->actingAs($editor, 'cms')
            ->postJson($this->api("header/items/{$design->getKey()}/move"), [
                'parent_id' => null,
                'index' => 0,
            ])
            ->assertOk();

        $this->assertNull($design->refresh()->parent_id);
        $this->assertSame(['Design', 'Services'], $this->labels('header'));
    }

    /**
     * A position past the end of a level is the end of it.
     *
     * What a drop onto the empty space under the last row means, and what the screen sends when
     * it counts a level that has meanwhile lost a row.
     */
    public function test_a_position_past_the_end_is_the_end(): void
    {
        $menu = $this->menu('header');

        $a = $this->item(['title' => ['en' => 'A']], $menu);
        $this->item(['title' => ['en' => 'B']], $menu);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("header/items/{$a->getKey()}/move"), ['parent_id' => null, 'index' => 99])
            ->assertOk();

        $this->assertSame(['B', 'A'], $this->labels('header'));
    }

    /**
     * Into its own subtree: the tree would come apart, and nobody meant it.
     *
     * The drag itself cannot express this — a list does not contain itself — but an API call can,
     * and the tools of the next session come through the same door.
     */
    public function test_an_item_cannot_be_moved_under_itself(): void
    {
        $menu = $this->menu('header');

        $services = $this->item(['title' => ['en' => 'Services']], $menu);
        $design = $this->item(['title' => ['en' => 'Design']], $menu, $services);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("header/items/{$services->getKey()}/move"), [
                'parent_id' => $design->getKey(),
                'index' => 0,
            ])
            ->assertNotFound();

        $this->assertSame($services->getKey(), $design->refresh()->parent_id);
    }

    public function test_an_item_cannot_be_moved_under_a_parent_from_another_menu(): void
    {
        $item = $this->item(['title' => ['en' => 'A']], $this->menu('header'));
        $elsewhere = $this->item(['title' => ['en' => 'Terms']], $this->menu('footer'));

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("header/items/{$item->getKey()}/move"), [
                'parent_id' => $elsewhere->getKey(),
                'index' => 0,
            ])
            ->assertNotFound();

        $this->assertNull($item->refresh()->parent_id);
    }

    public function test_a_viewer_cannot_move_anything(): void
    {
        $item = $this->item(['title' => ['en' => 'A']], $this->menu('header'));

        $this->actingAs($this->editor(['menu.view']), 'cms')
            ->postJson($this->api("header/items/{$item->getKey()}/move"), ['parent_id' => null, 'index' => 0])
            ->assertForbidden();
    }

    public function test_a_subtree_travels_with_the_item_it_hangs_from(): void
    {
        $menu = $this->menu('header');

        $services = $this->item(['title' => ['en' => 'Services']], $menu);
        $design = $this->item(['title' => ['en' => 'Design']], $menu, $services);
        $about = $this->item(['title' => ['en' => 'About']], $menu);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("header/items/{$services->getKey()}/move"), [
                'parent_id' => $about->getKey(),
                'index' => 0,
            ])
            ->assertOk();

        $design->refresh();

        $this->assertSame($services->getKey(), $design->parent_id);
        $this->assertSame(2, $design->depth);
        $this->assertSame(1, MenuItem::query()->whereNull('parent_id')->count());
    }
}
