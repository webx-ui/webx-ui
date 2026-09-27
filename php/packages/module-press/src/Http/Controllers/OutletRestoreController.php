<?php

declare(strict_types=1);

namespace WebxUi\Press\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Press\Http\Resources\OutletResource;
use WebxUi\Press\Models\Outlet;

/**
 * Out of the bin — bound by hand, because a deleted outlet is invisible to the model binding
 * every other endpoint here uses. It comes back to its place in the list and with its articles,
 * which it kept while it was away; the save that restores it gives it its addresses again. The
 * answer is the row of the list, as it will be drawn.
 */
final class OutletRestoreController
{
    public function __invoke(int $outlet): JsonResponse
    {
        $trashed = Outlet::withTrashed()->findOrFail($outlet);

        $trashed->restore();

        return ApiResponse::data(new OutletResource($trashed->refresh()));
    }
}
