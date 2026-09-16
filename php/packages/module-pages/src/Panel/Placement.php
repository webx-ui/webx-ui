<?php

declare(strict_types=1);

namespace WebxUi\Pages\Panel;

use WebxUi\Pages\Exceptions\PagesException;
use WebxUi\Pages\Models\Page;

/**
 * Where a page may be put, and what it costs to put it there (§5).
 *
 * The three refusals are rules about the tree rather than about the request that carried them,
 * so they live beside the model and not inside a controller: the panel's move endpoint and the
 * agent's `pages_move` ask the same question and have to get the same answer. A second copy of
 * them would be the one that forgets the home page.
 */
final class Placement
{
    /** Where a page can land relative to another one. */
    public const ZONES = ['before', 'after', 'inside'];

    /**
     * Put the page there, and say how many addresses changed because of it.
     *
     * The count is the page and everything under it: moving a branch rewrites every address in
     * it and leaves a redirect on each of the old ones, and whoever asked for the move has to
     * be told that out loud rather than find it in a report a month later.
     */
    public static function apply(Page $page, Page $target, string $zone): int
    {
        self::assert($page, $target, $zone);

        match ($zone) {
            'before' => $page->insertBefore($target),
            'after' => $page->insertAfter($target),
            default => $page->appendTo($target),
        };

        $page->refresh();

        return $page->descendants()->count() + 1;
    }

    /**
     * @throws PagesException when the tree would not survive the move
     */
    public static function assert(Page $page, Page $target, string $zone): void
    {
        // First, because every other refusal below would also be true of the home page and
        // would say the wrong thing about it: everything on the site is inside it.
        if ($page->isRoot()) {
            throw PagesException::homeCannotBeMoved();
        }

        // Beside the home page is where a second root would be, and the tree has one (§2.4).
        // Inside it is the ordinary case: every page of the site is under the home page.
        if ($target->isRoot() && $zone !== 'inside') {
            throw PagesException::homeHasNoSiblings();
        }

        // A page cannot land in its own branch — it would be its own ancestor, and the bounds
        // that say so would have nowhere to go.
        if ($target->is($page) || $target->isDescendantOf($page)) {
            throw PagesException::pageCannotHoldItself();
        }
    }
}
