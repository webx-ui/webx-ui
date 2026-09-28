<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * `POST vacancies/reorder { ids }` — the one order vacancies have, the same in every group of the
 * index.
 *
 * Not the shared `CategoryRoutes::items()`, which takes a `category` and writes the order inside
 * it: a vacancy has no place of its own inside a category. The ids are the list as the editor sees
 * it — the open ones, or all of them — and they take the places they hold now, in the new order:
 * the Open tab drags among the open ones, and the closed ones between them stay where they were.
 * One transaction.
 */
final class VacancyOrderController
{
    public function __invoke(Request $request): JsonResponse
    {
        $ids = $request->input('ids');

        if (! is_array($ids) || $ids === [] || array_filter($ids, static fn (mixed $id): bool => ! is_numeric($id)) !== []) {
            throw ValidationException::withMessages(['ids' => (string) __('validation.array', ['attribute' => 'ids'])]);
        }

        $ids = array_values(array_unique(array_map(intval(...), $ids)));

        if (Vacancy::query()->withTrashed()->whereKey($ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['ids' => (string) __('webx-vacancies::errors.unknown-vacancy')]);
        }

        (new Vacancy)->getConnection()->transaction(static function () use ($ids): void {
            // Places shared by two rows (a list nobody dragged yet) are made a run first, in the
            // order the list shows them: handing a shared place out twice would tie them again.
            $all = Vacancy::query()->withTrashed()->orderBy('position')->orderBy('id')->pluck('position', 'id');

            if ($all->unique()->count() !== $all->count()) {
                foreach ($all->keys()->values() as $place => $id) {
                    Vacancy::query()->withTrashed()->whereKey($id)->update(['position' => $place]);
                }
            }

            /** @var list<int> $slots */
            $slots = Vacancy::query()->withTrashed()->whereKey($ids)->orderBy('position')->pluck('position')->map(intval(...))->all();

            foreach ($ids as $n => $id) {
                Vacancy::query()->withTrashed()->whereKey($id)->update(['position' => $slots[$n]]);
            }
        });

        return ApiResponse::noContent();
    }
}
