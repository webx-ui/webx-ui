<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tree;

use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;

/**
 * Where an item sits: under which item, and how far down.
 *
 * One place rather than one per caller. The panel drags an item and an agent names a position,
 * and both mean the same thing — "this many siblings from the top of that level" — so the
 * arithmetic that turns a position into a neighbour lives here and is written once.
 *
 * Nothing in here reads the bounds of an item: an item loaded a moment ago holds the bounds it
 * had then, and a tree that has been dragged twice is a tree where those are stale (§10).
 */
final class Placement
{
    /**
     * Would this move put an item inside its own branch?
     *
     * The tree would then have no root and the nested set would be two disconnected halves.
     * Refused by whoever asked rather than corrected here — nobody meant it, and the two
     * callers say no in different words.
     */
    public static function loops(MenuItem $moved, ?MenuItem $parent): bool
    {
        return $parent instanceof MenuItem && ($parent->is($moved) || $parent->isDescendantOf($moved));
    }

    /**
     * Put the item at a position among the children of a parent, or among the roots.
     *
     * The siblings are read now and with the item itself left out, because a position counted
     * over a list the item is still in means one thing before the move and another after it.
     */
    public static function apply(Menu $menu, MenuItem $moved, ?MenuItem $parent, int $index): void
    {
        $siblings = MenuItem::query()
            ->where('menu_id', $menu->getKey())
            ->where('parent_id', $parent?->getKey())
            ->whereKeyNot($moved->getKey())
            ->ordered()
            ->get()
            ->values();

        $before = $siblings->get($index);
        $after = $index > 0 ? $siblings->get($index - 1) : null;

        if ($after instanceof MenuItem) {
            $moved->insertAfter($after);

            return;
        }

        if ($before instanceof MenuItem) {
            $moved->insertBefore($before);

            return;
        }

        // The level is empty, or the position is past its end: the item is simply the last
        // thing in it, which is what both of those mean.
        if ($parent instanceof MenuItem) {
            $moved->appendTo($parent);

            return;
        }

        $moved->saveAsRoot();
    }
}
