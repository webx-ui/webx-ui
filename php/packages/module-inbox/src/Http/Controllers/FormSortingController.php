<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Inbox\Http\Requests\SortingRequest;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Panel\Sorting;

/** The order of the left column, as somebody dragged it. */
final class FormSortingController
{
    public function __invoke(SortingRequest $request): JsonResponse
    {
        Sorting::apply(Form::query(), $request->ids());

        return ApiResponse::noContent();
    }
}
