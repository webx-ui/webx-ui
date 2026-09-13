<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Media\Http\Requests\ImageEditRequest;
use WebxUi\Media\Http\Resources\FileResource;
use WebxUi\Media\Images\ImageEditing;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;

final class ImageController
{
    public function __construct(
        private readonly ImageEditing $editing,
        private readonly FileStore $files,
    ) {}

    public function edit(ImageEditRequest $request, MediaFile $file): JsonResponse
    {
        if (! $file->isImage()) {
            return new JsonResponse(['message' => __('webx-media::errors.not-an-image')], 422);
        }

        /** @var array<string, mixed> $operations */
        $operations = $request->validated();

        return ApiResponse::data(new FileResource($this->editing->apply($file, $operations)));
    }

    /** The same picture as a second file, for an edit that should not replace the original. */
    public function copy(MediaFile $file): JsonResponse
    {
        $copy = $this->files->copy($file, (string) __('webx-media::files.copy', ['name' => $file->name]));

        return ApiResponse::data(new FileResource($copy), 201);
    }

    public function restore(MediaFile $file): JsonResponse
    {
        return ApiResponse::data(new FileResource($this->editing->restore($file)));
    }
}
