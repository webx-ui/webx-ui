<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Pages\Http\Resources\PageResource;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\Editors;
use WebxUi\Pages\Panel\Placement;

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
            'zone' => ['required', 'string', 'in:'.implode(',', Placement::ZONES)],
        ]);

        $target = Page::query()->findOrFail((int) $validated['target']);

        $changed = Placement::apply($page, $target, (string) $validated['zone']);

        return ApiResponse::data([
            'page' => new PageResource($page->loadMissing('routes')->loadCount('children'), Editors::of([$page])),
            // The page itself and everything under it: each of them is now at a different
            // address than it was a moment ago.
            'addresses_changed' => $changed,
        ]);
    }
}
