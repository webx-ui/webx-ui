<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Localization\Locales;
use WebxUi\Tariffs\Http\Resources\TariffResource;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Models\TariffCategory;
use WebxUi\Tariffs\Panel\TariffForm;
use WebxUi\Tariffs\Panel\TariffList;

/**
 * The section's list, and one tariff as its editor opens it (§5.4).
 *
 * The record is thin on purpose: the form is a described screen, so what a tariff's values are is
 * decided by the description and checked by `ScreenValues`.
 */
final class TariffController
{
    public function __construct(
        private readonly TariffList $list,
        private readonly TariffForm $form,
    ) {}

    /**
     * Every tariff at once, with the groups the list can be narrowed to beside them: the screen
     * cannot draw its filter without them.
     */
    public function index(Request $request, Locales $locales): JsonResponse
    {
        $locale = $locales->current();

        return new JsonResponse([
            'data' => $this->list->build($request)->get()
                ->map(static fn (Tariff $tariff): array => (new TariffResource($tariff))->resolve($request))
                ->values()
                ->all(),
            'filters' => [
                'categories' => TariffCategory::query()
                    ->ordered()
                    ->get()
                    ->map(static fn (TariffCategory $category): array => [
                        'id' => (int) $category->getKey(),
                        'title' => $category->displayName($locale),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function show(Tariff $tariff): JsonResponse
    {
        return ApiResponse::data($this->form->describe($tariff));
    }

    /**
     * A new tariff, from the values of the same screen that edits it: the list opens an empty
     * form rather than a dialog, and the first save is the one that creates the row.
     */
    public function store(Request $request): JsonResponse
    {
        $tariff = $this->form->save(new Tariff, $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($tariff), 201);
    }

    /** Save — 422 under the name of the field that refused. */
    public function update(Request $request, Tariff $tariff): JsonResponse
    {
        $tariff = $this->form->save($tariff, $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($tariff));
    }

    /** Into the bin: it comes back to its places in the list, in its groups and its services. */
    public function destroy(Tariff $tariff): JsonResponse
    {
        $tariff->delete();

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Request $request): array
    {
        $values = $request->input('values');

        return is_array($values) ? $values : [];
    }

    /**
     * @return callable(string): bool
     */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
