<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Vacancies\Http\Resources\VacancyResource;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * Out of the bin — bound by hand, because a deleted vacancy is invisible to the model binding
 * every other endpoint here uses.
 *
 * The address comes back by itself, or the restore is refused because somebody has taken the
 * spelling meanwhile: `OnConflict::Fail` makes that a 422 under the slug. A vacancy quietly
 * restored to `developer-2` is worse than one that says the place is occupied.
 */
final class VacancyRestoreController
{
    public function __invoke(int $vacancy): JsonResponse
    {
        $trashed = Vacancy::withTrashed()->findOrFail($vacancy);

        $trashed->restore();

        return ApiResponse::data(new VacancyResource(
            $trashed->refresh()->loadMissing(['routes', 'categories']),
        ));
    }
}
