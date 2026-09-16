<?php

declare(strict_types=1);

namespace WebxUi\Pages\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Pages\Http\Resources\PageResource;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Panel\Editors;

/**
 * On the site and off it.
 *
 * Both are `module-admin`'s, whole: publishing copies the draft into the columns, stamps the
 * date, writes the version and drops the autosaves it was insuring; unpublishing takes the page
 * off the site and touches nothing else, so what was being prepared is still being prepared.
 *
 * The address stays in the registry either way — whether a page answers is the handler's
 * question, not the registry's (§5), and an address released on unpublishing would be taken by
 * the next page called the same thing.
 */
final class PagePublicationController
{
    public function publish(Request $request, Page $page): JsonResponse
    {
        $page->publish($this->author($request), EntityVersion::SOURCE_PANEL);
        $page->refresh()->loadMissing('routes')->loadCount('children');

        return ApiResponse::data(new PageResource($page, Editors::of([$page])));
    }

    public function unpublish(Page $page): JsonResponse
    {
        $page->unpublish();
        $page->refresh()->loadMissing('routes')->loadCount('children');

        return ApiResponse::data(new PageResource($page, Editors::of([$page])));
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
