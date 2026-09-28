<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Http\Controllers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Localization\Locales;
use WebxUi\Vacancies\Http\Requests\VacancyRequest;
use WebxUi\Vacancies\Http\Resources\VacancyResource;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Models\VacancyCategory;
use WebxUi\Vacancies\Panel\Duplicate;
use WebxUi\Vacancies\Panel\Revision;
use WebxUi\Vacancies\Panel\VacancyForm;
use WebxUi\Vacancies\Panel\VacancyList;

/**
 * The section's list, and one vacancy as its editor opens it (§4.11).
 *
 * The form is a described screen, so what a vacancy's values are is decided by the description
 * and checked by `ScreenValues`. What is left here is what the screen cannot answer — whether
 * this editor is writing over somebody else — and that a save, a new vacancy and a copy are each
 * one transaction: a refusal half way through leaves nothing behind.
 */
final class VacancyController
{
    public function __construct(
        private readonly VacancyList $list,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * The whole list — no pages, the order is dragged by hand — with what it can be narrowed to
     * beside it: the screen cannot draw its filters without them.
     */
    public function index(Request $request, Locales $locales): JsonResponse
    {
        $locale = $locales->current();

        $rows = $this->list->build($request)->get()
            ->map(static fn (Vacancy $vacancy): array => (new VacancyResource($vacancy))->resolve($request))
            ->values()
            ->all();

        return new JsonResponse([
            'data' => $rows,
            'filters' => [
                'categories' => VacancyCategory::query()->ordered()->get()->map(static fn (VacancyCategory $category): array => [
                    'id' => (int) $category->getKey(),
                    'title' => $category->displayName($locale),
                ])->values()->all(),
            ],
        ]);
    }

    public function show(Request $request, Vacancy $vacancy, VacancyForm $form): JsonResponse
    {
        return ApiResponse::data($form->describe($this->loaded($vacancy), $this->author($request)));
    }

    /**
     * A new vacancy: a title and the address made of it, as a draft — the registry holds its
     * address from the start, answering 404, so nobody else takes it while it is being written.
     * On site, full time, in the first currency of the site and its country (§4.11). In a
     * transaction: an address refused leaves no vacancy without one.
     */
    public function store(VacancyRequest $request, VacancyForm $form): JsonResponse
    {
        $vacancy = $this->db->transaction(static function () use ($request, $form): Vacancy {
            $vacancy = $form->blank($request->title(), $request->slug());
            $vacancy->save();

            return $vacancy;
        });

        return ApiResponse::data($form->describe($this->loaded($vacancy->refresh()), $this->author($request)), 201);
    }

    /**
     * Save the draft. A request that names no revision did not read the vacancy first — an
     * import, a script — and is let through: there is no editor to surprise.
     */
    public function update(Request $request, Vacancy $vacancy, VacancyForm $form): JsonResponse
    {
        $sent = $request->input('revision');

        if (is_string($sent) && $sent !== Revision::of($vacancy)) {
            return new JsonResponse([
                'message' => (string) __('webx-vacancies::errors.conflict'),
                'data' => $form->describe($this->loaded($vacancy), $this->author($request)),
            ], 409);
        }

        $user = $request->user();
        $input = $request->input('values');
        $author = $this->author($request);

        $this->db->transaction(static fn () => $form->save(
            $vacancy,
            is_array($input) ? $input : [],
            static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission),
            $author,
        ));

        return ApiResponse::data($form->describe($this->loaded($vacancy->refresh()), $author));
    }

    /**
     * "Duplicate" (decision 20): the form of the copy, which the panel opens next.
     */
    public function duplicate(Request $request, Vacancy $vacancy, Duplicate $duplicate, VacancyForm $form): JsonResponse
    {
        $copy = $duplicate->of($vacancy);

        return ApiResponse::data($form->describe($this->loaded($copy), $this->author($request)), 201);
    }

    /** Into the bin, and the address with it. */
    public function destroy(Vacancy $vacancy): JsonResponse
    {
        $vacancy->delete();

        return ApiResponse::noContent();
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
