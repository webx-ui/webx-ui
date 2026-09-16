<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Panel\Authors;
use WebxUi\Pages\Http\Resources\PageVersionResource;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\PageForm;

/**
 * The history of a page: what was published, when, by whom and from where (§6).
 *
 * Only the publications. The autosaves that `saveDraft()` writes are insurance and not history
 * — a ring of the last few copies of the draft, unnumbered, replaced every couple of minutes —
 * and a list that mixed them in would be a list nobody can read.
 *
 * Restoring is not "put it back on the site": the old version becomes the draft, and publishing
 * it is the same separate step it always is. The history stays a line, and the site changes
 * only when somebody says so.
 */
final class PageVersionController
{
    public function index(Page $page): JsonResponse
    {
        $versions = $page->publishedVersions()->get();
        $authors = Authors::names($versions->map(static fn (EntityVersion $version): ?int => $version->author_id));

        return ApiResponse::data(
            $versions
                ->map(static fn (EntityVersion $version): PageVersionResource => new PageVersionResource($version, $authors))
                ->values()
                ->all(),
        );
    }

    public function restore(Request $request, Page $page, int $number, PageForm $form): JsonResponse
    {
        $version = $page->publishedVersions()->where('number', $number)->first();

        if (! $version instanceof EntityVersion) {
            throw new NotFoundHttpException;
        }

        $page->restoreVersion($version);
        $page->refresh()->loadMissing('routes')->loadCount('children');

        $id = $request->user()?->getAuthIdentifier();

        // The whole page rather than the values alone: restoring puts the page into "edited"
        // and gives it a new revision, and a form that took only the values back would save
        // over somebody with a revision that is one edit old.
        return ApiResponse::data($form->describe($page, is_int($id) ? $id : null));
    }
}
