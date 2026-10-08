<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Editing\HeldRevision;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Vacancies\Http\Resources\VacancyResource;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Panel\Closing;

/**
 * On the site and off it — and, from the menu of a row, the hiring closed and opened again
 * (§4.11). No date: a vacancy is on the site or it is not; its last day is a field of its own.
 *
 * The first publication sets the day it was put up, when nobody has (decision 15) — the model does
 * that on every door, not this controller.
 */
final class VacancyPublicationController
{
    public function publish(Request $request, Vacancy $vacancy): JsonResponse
    {
        // The editor sends the revision it held: an edit it has not seen is not published under it.
        $stale = HeldRevision::conflict($request, 'vacancies', (string) $vacancy->getKey());

        if ($stale !== null) {
            return $stale;
        }

        $vacancy->publish($this->author($request), EntityVersion::SOURCE_PANEL);

        return ApiResponse::data(new VacancyResource($this->loaded($vacancy->refresh())));
    }

    /**
     * Off the site, and nothing else touched: what was being prepared is still being prepared,
     * and the address stays held.
     */
    public function unpublish(Vacancy $vacancy): JsonResponse
    {
        $vacancy->unpublish();

        return ApiResponse::data(new VacancyResource($this->loaded($vacancy->refresh())));
    }

    /** Close the hiring: a save and a publication in one transaction, or a 409 saying why not. */
    public function close(Request $request, Vacancy $vacancy, Closing $closing): JsonResponse
    {
        $refusal = $closing->refusal($vacancy);

        if ($refusal !== null) {
            return new JsonResponse(['message' => $refusal], 409);
        }

        $closing->close($vacancy, $this->author($request));

        return ApiResponse::data(new VacancyResource($this->loaded($vacancy->refresh())));
    }

    /** Open it again — an expired one without its last day, or it would stay closed. */
    public function reopen(Request $request, Vacancy $vacancy, Closing $closing): JsonResponse
    {
        $refusal = $closing->refusal($vacancy);

        if ($refusal !== null) {
            return new JsonResponse(['message' => $refusal], 409);
        }

        $closing->reopen($vacancy, $this->author($request));

        return ApiResponse::data(new VacancyResource($this->loaded($vacancy->refresh())));
    }

    private function loaded(Vacancy $vacancy): Vacancy
    {
        return $vacancy->loadMissing(['routes', 'categories']);
    }

    private function author(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }
}
