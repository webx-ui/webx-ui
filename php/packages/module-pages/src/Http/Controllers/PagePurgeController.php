<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Pages\Models\Page;

/**
 * Out of the bin for good: a page and its branch, or the whole bin — and how many pages the whole
 * bin is, which the panel asks before it asks the editor.
 *
 * Bound by hand, like the restore: a page in the bin is invisible to the model binding. A page
 * that is not in the bin is refused — deleting for good is a second step, never the first.
 */
final class PagePurgeController
{
    public function page(int $page): JsonResponse
    {
        $trashed = Page::withTrashed()->findOrFail($page);

        return ApiResponse::data(['purged' => $trashed->purgeBranch()]);
    }

    /**
     * Every page in the bin, including those that went in with a parent: the list shows only the
     * top of each branch, and emptying the bin takes all of them.
     */
    public function count(): JsonResponse
    {
        return ApiResponse::data(['pages' => Page::onlyTrashed()->count()]);
    }

    public function bin(): JsonResponse
    {
        return ApiResponse::data(['purged' => Page::purgeBin()]);
    }
}
