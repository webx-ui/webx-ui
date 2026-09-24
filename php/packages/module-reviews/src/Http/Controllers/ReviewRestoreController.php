<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Reviews\Http\Resources\ReviewResource;
use WebxUi\Reviews\Models\Review;

/**
 * Out of the bin — bound by hand, because a deleted review is invisible to the model binding
 * every other endpoint here uses. It comes back to its places in the list and in its categories,
 * which it kept while it was away. The answer is the row of the list, as it will be drawn.
 */
final class ReviewRestoreController
{
    public function __invoke(int $review): JsonResponse
    {
        $trashed = Review::withTrashed()->findOrFail($review);

        $trashed->restore();

        return ApiResponse::data(new ReviewResource($trashed->refresh()->loadMissing('categories')));
    }
}
