<?php

declare(strict_types=1);

namespace WebxUi\Banners\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Banners\Http\Resources\BannerResource;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Models\Place;
use WebxUi\Banners\Panel\BannerForm;
use WebxUi\Banners\Panel\PlaceEditor;
use WebxUi\Banners\Places;
use WebxUi\Media\Screens\MediaFiles;

/**
 * The places and what is in them (§5.6): the list, a place of somebody's own, the banners of one
 * place, a new banner in it and their order.
 *
 * Everything addresses a place by its key rather than its id, because for part of its life a
 * declared place has no row: it is on the screen before anything was saved into it.
 */
final class PlaceController
{
    public function __construct(
        private readonly Places $places,
        private readonly PlaceEditor $editor,
        private readonly BannerForm $form,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::data($this->editor->all());
    }

    public function store(Request $request): JsonResponse
    {
        $place = $this->editor->create($request->input('key'), $request->input('title'));

        return ApiResponse::data($this->editor->one($place->key), 201);
    }

    /** Only one of somebody's own: a declared place is named by the configuration. */
    public function update(Request $request, string $key): JsonResponse
    {
        $this->refuseDeclared($key);

        $place = $this->editor->rename($this->row($key), $request->input('title'));

        return ApiResponse::data($this->editor->one($place->key));
    }

    /**
     * Only an empty one of somebody's own. A place with banners — the bin counts — is a 422 that
     * says how many, in words under `place` and as a number beside them.
     */
    public function destroy(string $key): JsonResponse
    {
        $this->refuseDeclared($key);

        $place = $this->row($key);
        $count = $this->editor->bannersIn($place);

        if ($count > 0) {
            $message = $this->editor->notEmpty($count);

            return new JsonResponse(['message' => $message, 'errors' => ['place' => [$message]], 'count' => $count], 422);
        }

        $this->editor->delete($place);

        return ApiResponse::noContent();
    }

    /**
     * The banners of one place in their order, or its bin — the last thing thrown away first. A
     * declared place without a row has none yet.
     */
    public function banners(Request $request, MediaFiles $files, string $key): JsonResponse
    {
        $this->known($key);

        $place = Place::query()->where('key', $key)->first();

        if ($place === null) {
            return ApiResponse::data([]);
        }

        $query = Banner::query()->where('place_id', $place->getKey());

        $banners = $request->boolean('trashed')
            ? $query->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id')->get()
            : $query->orderBy('position')->orderBy('id')->get();

        // The thumbnails of the whole list in one query of the library.
        $files->load(array_values(array_filter($banners->map(
            static fn (Banner $banner): ?string => $banner->mediaPath('image'),
        )->all())));

        return ApiResponse::data($banners
            ->map(static fn (Banner $banner): array => (new BannerResource($banner))->resolve($request))
            ->values()
            ->all());
    }

    /**
     * A new banner at the end of the place, from the values of the same screen that edits it: the
     * list opens an empty form rather than a dialog, and the first save is the one that creates
     * the row — and, for a declared place, the row of the place.
     */
    public function storeBanner(Request $request, string $key): JsonResponse
    {
        $this->known($key);

        $banner = $this->form->save(new Banner, $this->values($request), $key, $this->can($request));

        return ApiResponse::data($this->form->describe($banner), 201);
    }

    /** The order inside the place, dragged the way the editor sees it. */
    public function reorder(Request $request, string $key): JsonResponse
    {
        $this->known($key);

        $ids = $request->input('ids');

        if (! is_array($ids) || ! array_is_list($ids) || array_filter($ids, static fn (mixed $id): bool => ! is_numeric($id)) !== []) {
            throw ValidationException::withMessages(['ids' => (string) __('webx-banners::errors.ids')]);
        }

        $this->editor->reorder($key, array_map(intval(...), $ids));

        return ApiResponse::noContent();
    }

    /** A key that is neither declared nor made is not a place: 404. */
    private function known(string $key): void
    {
        if (! $this->places->exists($key)) {
            throw new NotFoundHttpException;
        }
    }

    private function row(string $key): Place
    {
        $place = Place::query()->where('key', $key)->first();

        if (! $place instanceof Place) {
            throw new NotFoundHttpException;
        }

        return $place;
    }

    private function refuseDeclared(string $key): void
    {
        if ($this->places->isDeclared($key)) {
            throw new AccessDeniedHttpException((string) __('webx-banners::errors.place-declared'));
        }
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
