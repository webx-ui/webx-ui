<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Pages\Http\Resources\PageResource;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\Editors;

/**
 * Out of the bin, with whatever went in with it (§7).
 *
 * Bound by hand rather than by the route, because a trashed page is invisible to the model
 * binding that every other endpoint here uses — and this is the one endpoint whose whole
 * subject is a page nobody can see.
 *
 * The address comes back by itself, or the restore is refused because somebody has taken it in
 * the meantime — a `PathRejected` is a 422 on the slug, which is the right answer: a page
 * quietly restored to a different address is worse than one that says the place is occupied.
 *
 * Its former addresses come back too, each one that nobody took meanwhile; the ones somebody
 * did take are named in `aliases_dropped`, because those old links now open somebody else.
 */
final class PageRestoreController
{
    public function __invoke(int $page): JsonResponse
    {
        $trashed = Page::withTrashed()->findOrFail($page);

        $count = $trashed->trashedBranch()->count() + 1;

        $trail = $trashed->restoreBranchWithTrail();

        return ApiResponse::data([
            'page' => new PageResource($trashed->refresh()->loadMissing('routes')->loadCount('children'), Editors::of([$trashed])),
            'restored' => $count,
            'aliases_restored' => $trail->restored ?? [],
            'aliases_dropped' => $trail->dropped ?? [],
        ]);
    }
}
