<?php

declare(strict_types=1);

namespace WebxUi\Faq\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Faq\Http\Resources\QuestionResource;
use WebxUi\Faq\Models\Question;

/**
 * Out of the bin — bound by hand, because a deleted question is invisible to the model binding
 * every other endpoint here uses. It comes back to its places in the list and in its categories,
 * which it kept while it was away.
 */
final class QuestionRestoreController
{
    public function __invoke(int $question): JsonResponse
    {
        $trashed = Question::withTrashed()->findOrFail($question);

        $trashed->restore();

        return ApiResponse::data(new QuestionResource($trashed->refresh()->loadMissing('categories')));
    }
}
