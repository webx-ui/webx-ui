<?php

declare(strict_types=1);

namespace WebxUi\Press\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Press\Http\Resources\OutletResource;
use WebxUi\Press\Models\Outlet;
use WebxUi\Press\Panel\OutletForm;
use WebxUi\Press\Panel\OutletList;

/**
 * The section's list, one outlet as its editor opens it, and the order (§4.10).
 *
 * The record is thin on purpose: the form is a described screen, so what an outlet's values are is
 * decided by the description and checked by `ScreenValues` and {@see OutletForm}.
 */
final class OutletController
{
    public function __construct(
        private readonly OutletList $list,
        private readonly OutletForm $form,
    ) {}

    /** Every outlet at once — the list is where they are put in order, so it has no pages. */
    public function index(Request $request, MediaFiles $files): JsonResponse
    {
        $outlets = $this->list->build($request)->get();

        // The thumbnails of the whole list in one query of the library.
        $files->load(array_values(array_filter($outlets->map(static fn (Outlet $outlet): ?string => $outlet->logoPath())->all())));

        return new JsonResponse([
            'data' => $outlets
                ->map(static fn (Outlet $outlet): array => (new OutletResource($outlet))->resolve($request))
                ->values()
                ->all(),
        ]);
    }

    public function show(Outlet $outlet): JsonResponse
    {
        return ApiResponse::data($this->form->describe($outlet));
    }

    /**
     * A new outlet, from the values of the same screen that edits it: the list opens an empty
     * form rather than a dialog, and the first save is the one that creates the row.
     */
    public function store(Request $request): JsonResponse
    {
        $outlet = $this->form->save(new Outlet, $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($outlet), 201);
    }

    /** Save — 422 under the name of the field that refused. */
    public function update(Request $request, Outlet $outlet): JsonResponse
    {
        $outlet = $this->form->save($outlet, $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($outlet));
    }

    /** Into the bin, with its articles: it comes back to its place and with them. */
    public function destroy(Outlet $outlet): JsonResponse
    {
        $outlet->delete();

        return ApiResponse::noContent();
    }

    /** The whole list in the order it was dragged into (decision 6). */
    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->input('ids');

        Ordering::move(Outlet::class, is_array($ids) ? array_values(array_filter($ids, is_numeric(...))) : []);

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
