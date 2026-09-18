<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Inbox\Http\Requests\SortingRequest;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Panel\Sorting;

/**
 * The order the questions are asked in.
 *
 * Scoped to the form in the address: the ids arrive from a list the panel drew, and a list
 * drawn from one form must not be able to renumber another one's.
 */
final class FieldSortingController
{
    public function __invoke(SortingRequest $request, Form $form): JsonResponse
    {
        Sorting::apply($form->fields()->getQuery()->reorder(), $request->ids());

        return ApiResponse::noContent();
    }
}
