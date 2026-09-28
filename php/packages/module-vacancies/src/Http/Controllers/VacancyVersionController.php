<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Support\Authors;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Vacancies\Http\Resources\VacancyVersionResource;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Panel\VacancyForm;

/**
 * The history of a vacancy: what was published, when, by whom and from where.
 *
 * Only the publications — the autosaves are insurance, not history. Restoring is not "put it back
 * on the site": the old version becomes the draft, and publishing it is the same separate step.
 */
final class VacancyVersionController
{
    public function index(Request $request, Vacancy $vacancy): JsonResponse
    {
        $versions = $vacancy->publishedVersions()->get();
        $authors = Authors::names($request->user(), $versions->map(static fn (EntityVersion $version): ?int => $version->author_id));

        return ApiResponse::data(
            $versions
                ->map(static fn (EntityVersion $version): VacancyVersionResource => new VacancyVersionResource($version, $authors))
                ->values()
                ->all(),
        );
    }

    public function restore(Request $request, Vacancy $vacancy, int $number, VacancyForm $form): JsonResponse
    {
        $version = $vacancy->publishedVersions()->where('number', $number)->first();

        if (! $version instanceof EntityVersion) {
            throw new NotFoundHttpException;
        }

        $vacancy->restoreVersion($version);

        $id = $request->user()?->getAuthIdentifier();

        // The whole record rather than the values alone: restoring gives the vacancy a new
        // revision, and a form that took only the values would save over it one edit late.
        return ApiResponse::data($form->describe(
            $vacancy->refresh()->loadMissing(['routes', 'categories']),
            is_int($id) ? $id : null,
        ));
    }
}
