<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Vacancies\Panel\Reorder;

/**
 * `POST vacancies/reorder { ids }` — the one order vacancies have, the same in every group of the
 * index.
 *
 * Not the shared `CategoryRoutes::items()`, which takes a `category` and writes the order inside
 * it: a vacancy has no place of its own inside a category. How the ids take their places is
 * {@see Reorder}'s, shared with an agent's `vacancies_reorder`.
 */
final class VacancyOrderController
{
    public function __invoke(Request $request, Reorder $reorder): JsonResponse
    {
        $ids = $request->input('ids');

        if (! is_array($ids) || $ids === [] || array_filter($ids, static fn (mixed $id): bool => ! is_numeric($id)) !== []) {
            throw ValidationException::withMessages(['ids' => (string) __('validation.array', ['attribute' => 'ids'])]);
        }

        $reorder->move(array_values(array_map(intval(...), $ids)));

        return ApiResponse::noContent();
    }
}
