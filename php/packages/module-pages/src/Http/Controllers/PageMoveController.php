<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Pages\Exceptions\PagesException;
use WebxUi\Pages\Http\Resources\PageResource;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\Editors;

/**
 * Where a page sits, which is also what its address is (§5).
 *
 * The answer says how many addresses changed, because the panel has to say it out loud: moving
 * a branch rewrites every address under it and leaves a redirect on each of the old ones, and
 * an editor who learns that from the SEO report a month later learns it from the wrong person.
 */
final class PageMoveController
{
    public function __invoke(Request $request, Page $page): JsonResponse
    {
        $validated = $request->validate([
            'target' => ['required', 'integer'],
            'zone' => ['required', 'string', 'in:before,after,inside'],
        ]);

        $target = Page::query()->findOrFail((int) $validated['target']);
        $zone = (string) $validated['zone'];

        $this->assertPlaceable($page, $target, $zone);

        match ($zone) {
            'before' => $page->insertBefore($target),
            'after' => $page->insertAfter($target),
            default => $page->appendTo($target),
        };

        $page->refresh();

        return ApiResponse::data([
            'page' => new PageResource($page->loadMissing('routes')->loadCount('children'), Editors::of([$page])),
            // The page itself and everything under it: each of them is now at a different
            // address than it was a moment ago.
            'addresses_changed' => $page->descendants()->count() + 1,
        ]);
    }

    private function assertPlaceable(Page $page, Page $target, string $zone): void
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
