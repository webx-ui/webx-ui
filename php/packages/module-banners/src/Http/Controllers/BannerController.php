<?php

declare(strict_types=1);

namespace WebxUi\Banners\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Banners\Http\Resources\BannerResource;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Panel\BannerForm;

/**
 * One banner as the editor opens it, saves it, throws it away and brings it back (§5.6).
 *
 * The record is thin on purpose: the form is a described screen, so what a banner's values are is
 * decided by the description and checked by `ScreenValues`. The place travels beside the values
 * (decision 9): a key moves the banner to the end of that place.
 */
final class BannerController
{
    public function __construct(private readonly BannerForm $form) {}

    public function show(Banner $banner): JsonResponse
    {
        return ApiResponse::data($this->form->describe($banner));
    }

    /** Save — 422 under the name of the field that refused, or under `place`. */
    public function update(Request $request, Banner $banner): JsonResponse
    {
        $values = $request->input('values');
        $place = $request->input('place');

        $banner = $this->form->save(
            $banner,
            is_array($values) ? $values : [],
            is_string($place) && $place !== '' ? $place : null,
            $this->can($request),
        );

        return ApiResponse::data($this->form->describe($banner));
    }

    /** Into the bin: it comes back to its place and to its position there. */
    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();

        return ApiResponse::noContent();
    }

    /**
     * Out of the bin — bound by hand, because a deleted banner is invisible to the model binding
     * every other endpoint here uses. The place is always still there: only an empty one can be
     * deleted, and the bin counts. The answer is the row of the list, as it will be drawn.
     */
    public function restore(int $banner): JsonResponse
    {
        $trashed = Banner::withTrashed()->findOrFail($banner);

        $trashed->restore();

        return ApiResponse::data(new BannerResource($trashed->refresh()));
    }

    /**
     * @return callable(string): bool
     */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
