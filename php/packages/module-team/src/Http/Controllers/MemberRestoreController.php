<?php

declare(strict_types=1);

namespace WebxUi\Team\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Team\Http\Resources\MemberResource;
use WebxUi\Team\Models\Member;

/**
 * Out of the bin — bound by hand, because a deleted person is invisible to the model binding
 * every other endpoint here uses. They come back to their place in the list and to their services,
 * which they kept while they were away. The answer is the row of the list, as it will be drawn.
 */
final class MemberRestoreController
{
    public function __invoke(int $member): JsonResponse
    {
        $trashed = Member::withTrashed()->findOrFail($member);

        $trashed->restore();

        return ApiResponse::data(new MemberResource($trashed->refresh()));
    }
}
