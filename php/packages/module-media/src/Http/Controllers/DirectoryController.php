<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Media\Directories\DirectoryService;
use WebxUi\Media\Exceptions\RootIsImmutable;
use WebxUi\Media\Http\Requests\DirectoryMoveRequest;
use WebxUi\Media\Http\Requests\DirectoryStoreRequest;
use WebxUi\Media\Http\Requests\DirectoryUpdateRequest;
use WebxUi\Media\Http\Resources\DirectoryResource;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\NestedSet\Exceptions\NestedSetException;

final class DirectoryController
{
    public function __construct(private readonly DirectoryService $directories) {}

    /**
     * The whole tree in one answer.
     *
     * A library's folders are tens, not thousands, and the panel draws all of them at once — so
     * this is one query with the counts, nested in memory, rather than a request per level.
     */
    public function index(): JsonResponse
    {
        $nodes = MediaDirectory::query()->withCount('files')->ordered()->get();

        return ApiResponse::data(DirectoryResource::collection(MediaDirectory::toTree($nodes)));
    }

    public function store(DirectoryStoreRequest $request): JsonResponse
    {
        $parent = MediaDirectory::query()->findOrFail($request->integer('parent_id'));

        $directory = new MediaDirectory(['title' => (string) $request->string('title')]);
        $directory->appendTo($parent);

        return ApiResponse::data(new DirectoryResource($directory->refresh()), 201);
    }

    public function update(DirectoryUpdateRequest $request, MediaDirectory $directory): JsonResponse
    {
        $directory->update(['title' => (string) $request->string('title')]);

        return ApiResponse::data(new DirectoryResource($directory));
    }

    public function move(DirectoryMoveRequest $request, MediaDirectory $directory): JsonResponse
    {
        if ($directory->isLibraryRoot()) {
            throw new RootIsImmutable;
        }

        $parent = MediaDirectory::query()->findOrFail($request->integer('parent_id'));

        try {
            $this->place($directory, $parent, $request);
        } catch (NestedSetException $exception) {
            // A folder dropped inside itself. The tree refuses it; the panel needs to hear why
            // in words rather than as a 500.
            return new JsonResponse([
                'message' => __('webx-media::errors.directory-into-itself'),
                'code' => 'directory_into_itself',
            ], 422);
        }

        return ApiResponse::data(new DirectoryResource($directory->refresh()));
    }

    public function destroy(Request $request, MediaDirectory $directory): JsonResponse
    {
        $this->directories->delete($directory, $request->boolean('force'));

        return ApiResponse::noContent();
    }

    private function place(MediaDirectory $directory, MediaDirectory $parent, DirectoryMoveRequest $request): void
    {
        $before = $request->integer('before_id');
        $after = $request->integer('after_id');

        if ($before > 0) {
            $directory->insertBefore(MediaDirectory::query()->findOrFail($before));

            return;
        }

        if ($after > 0) {
            $directory->insertAfter(MediaDirectory::query()->findOrFail($after));

            return;
        }

        $directory->appendTo($parent);
    }
}
