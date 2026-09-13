<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Media\Http\Requests\FileDeleteRequest;
use WebxUi\Media\Http\Requests\FileIndexRequest;
use WebxUi\Media\Http\Requests\FileMoveRequest;
use WebxUi\Media\Http\Requests\FileRenameRequest;
use WebxUi\Media\Http\Requests\FileUploadRequest;
use WebxUi\Media\Http\Resources\FileResource;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Media\Support\MediaType;

final class FileController
{
    public function __construct(private readonly FileStore $files) {}

    /**
     * Always paginated, search included.
     *
     * This is the only thing standing between a folder of ten thousand photographs and a panel
     * that never finishes loading — which is why the reference this module is modelled on could
     * not be copied here.
     */
    public function index(FileIndexRequest $request): AnonymousResourceCollection
    {
        $query = MediaFile::query();

        if ($request->filled('directory_id')) {
            $query->where('directory_id', $request->integer('directory_id'));
        }

        if ($request->filled('q')) {
            $query->search((string) $request->string('q'));
        }

        if ($request->filled('type')) {
            MediaType::filter($query, (string) $request->string('type'));
        }

        // Counted before the page is cut, and over the same filters: the status bar under the
        // grid answers "how much is in here", which a page of twenty-four cannot.
        $stats = (clone $query)->toBase()->selectRaw('COUNT(*) as files, COALESCE(SUM(size), 0) as size')->first();

        $sort = (string) ($request->string('sort')->value() ?: '-created_at');
        $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc');

        return FileResource::collection($query->paginate($request->integer('per_page') ?: 24))
            ->additional([
                'stats' => [
                    'files' => (int) ($stats->files ?? 0),
                    'size' => (int) ($stats->size ?? 0),
                ],
            ]);
    }

    public function show(MediaFile $file): JsonResponse
    {
        return ApiResponse::data(new FileResource($file));
    }

    public function store(FileUploadRequest $request): JsonResponse
    {
        $directory = MediaDirectory::query()->findOrFail($request->integer('directory_id'));

        $stored = [];

        /** @var UploadedFile $upload */
        foreach ((array) $request->file('files') as $upload) {
            $file = $this->files->store($upload, $directory);

            // The same bytes were already in this folder: the panel says so instead of
            // pretending it added something.
            $stored[] = (new FileResource($file))->duplicate(! $file->wasRecentlyCreated);
        }

        return ApiResponse::data($stored, 201);
    }

    public function update(FileRenameRequest $request, MediaFile $file): JsonResponse
    {
        $file->update(['name' => (string) $request->string('name')]);

        return ApiResponse::data(new FileResource($file));
    }

    public function move(FileMoveRequest $request): JsonResponse
    {
        /** @var list<int> $ids */
        $ids = $request->input('ids', []);

        // A column, not a copy: a key says nothing about the folder it is in.
        $moved = MediaFile::query()
            ->whereIn('id', $ids)
            ->update(['directory_id' => $request->integer('directory_id')]);

        return ApiResponse::data(['moved' => $moved]);
    }

    public function destroyOne(MediaFile $file): JsonResponse
    {
        $this->files->deleteAll([$file]);

        return ApiResponse::noContent();
    }

    public function destroy(FileDeleteRequest $request): JsonResponse
    {
        /** @var list<int> $ids */
        $ids = $request->input('ids', []);

        $deleted = $this->files->deleteAll(MediaFile::query()->whereIn('id', $ids)->cursor());

        return ApiResponse::data(['deleted' => $deleted]);
    }
}
