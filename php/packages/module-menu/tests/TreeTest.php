<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use WebxUi\Menu\Models\MenuItem;

/**
 * One table, one tree per menu.
 *
 * The bounds of a nested set are only ever compared inside a scope, so the only way to find out
 * that the scope is wired up is to fill two menus and look: without it the second menu's items
 * would be numbered after the first one's, and a move in one would rewrite the other.
 */
class TreeTest extends TestCase
{
    public function test_two_menus_keep_separate_bounds(): void
    {
        $header = $this->menu('header');
        $footer = $this->menu('footer');

        $first = $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about'], $header);
        $second = $this->item(['title' => ['en' => 'Terms'], 'target' => 'url', 'url' => '/terms'], $footer);

        $this->assertSame([1, 2], [$first->lft, $first->rgt]);
        $this->assertSame([1, 2], [$second->lft, $second->rgt], 'The second menu starts its own numbering.');
    }

    public function test_moving_in_one_menu_leaves_the_other_alone(): void
    {
        $header = $this->menu('header');
        $footer = $this->menu('footer');

        $a = $this->item(['title' => ['en' => 'A'], 'target' => 'url', 'url' => '/a'], $header);
        $b = $this->item(['title' => ['en' => 'B'], 'target' => 'url', 'url' => '/b'], $header);
        $far = $this->item(['title' => ['en' => 'Far'], 'target' => 'url', 'url' => '/far'], $footer);

        $b->insertBefore($a);

        $this->assertSame(['B', 'A'], $this->labels('header'));
        $this->assertSame([1, 2], [$far->refresh()->lft, $far->rgt]);
    }

    public function test_children_are_read_under_their_own_parent(): void
    {
        $header = $this->menu('header');

        $services = $this->item(['title' => ['en' => 'Services'], 'target' => 'none', 'is_heading' => true], $header);
        $this->item(['title' => ['en' => 'Design'], 'target' => 'url', 'url' => '/design'], $header, $services);
        $this->item(['title' => ['en' => 'Code'], 'target' => 'url', 'url' => '/code'], $header, $services);

        $tree = menu('header');

        $this->assertSame(['Services'], $this->labels('header'));
        $this->assertSame(
            ['Design', 'Code'],
            $tree->first()->children->map(static fn ($link): string => $link->label)->all(),
        );
        $this->assertSame(
            ['Services', 'Design', 'Code'],
            $tree->flat()->map(static fn ($link): string => $link->label)->all(),
        );
    }

    public function test_an_item_takes_its_children_with_it_when_it_is_hidden(): void
    {
        $header = $this->menu('header');

        $services = $this->item(['title' => ['en' => 'Services'], 'target' => 'none', 'is_heading' => true], $header);
        $this->item(['title' => ['en' => 'Design'], 'target' => 'url', 'url' => '/design'], $header, $services);

        $services->update(['visible' => false]);

        $this->assertSame([], $this->labels('header'));
        $this->assertSame(2, MenuItem::query()->count(), 'Hiding an item does not delete anything.');
    }
}
