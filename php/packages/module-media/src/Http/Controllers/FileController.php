<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Mime\MimeTypes;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Uploads\ClaimedUpload;
use WebxUi\Admin\Uploads\Uploads;
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

    /**
     * The file behind a key.
     *
     * What an entity stores is the key and nothing else — that is what lets the library move to
     * another disk without rewriting a single record. A form editing that entity still has to
     * draw the picture, and it has only the key, so this is how it gets the rest.
     */
    public function byPath(Request $request): JsonResponse
    {
        $path = (string) $request->query('path');

        $file = MediaFile::query()->where('path', $path)->first();

        if ($file === null) {
            return ApiResponse::message(__('webx-media::errors.file-not-found'), 404);
        }

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

    /**
     * A file that arrived a piece at a time, handed to the library.
     *
     * The same way in as a multipart upload from here on: the same rules on the content — now
     * that it is whole, and its real type can be read rather than believed — then the same
     * pipeline, the same deduplication, and the same answer.
     */
    public function storeChunked(Request $request, Uploads $uploads): JsonResponse
    {
        $data = $request->validate([
            'directory_id' => ['required', 'integer', 'exists:media_directories,id'],
            'upload' => ['required', 'string', 'max:64'],
        ], [], ['directory_id' => (string) __('webx-media::validation.directory_id')]);

        $directory = MediaDirectory::query()->findOrFail((int) $data['directory_id']);

        // By whoever is asking: an id seen in somebody else's browser attaches nothing.
        $claimed = $uploads->claim((string) $data['upload'], FileStore::UPLOAD_PURPOSE, $request->user());

        try {
            // `test`, because this is not PHP's own upload and `is_uploaded_file()` would say so.
            $upload = new UploadedFile($claimed->path, $claimed->name, $this->typeOf($claimed), UPLOAD_ERR_OK, true);

            Validator::make(
                ['file' => $upload],
                ['file' => FileUploadRequest::fileRules()],
                FileUploadRequest::fileMessages('file'),
            )->validate();

            $file = $this->files->store($upload, $directory);
        } finally {
            // Stored or refused, the piece file is done with: the library made its own copy.
            $claimed->discard();
        }

        return ApiResponse::data((new FileResource($file))->duplicate(! $file->wasRecentlyCreated), 201);
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

    /**
     * What the browser declared, or else what the bytes are. A browser declares nothing for a
     * type it does not know — a HEIC on most of them — and the library records the type it
     * stores rather than `application/octet-stream`.
     */
    private function typeOf(ClaimedUpload $claimed): ?string
    {
        $declared = strtolower(trim(explode(';', $claimed->type)[0]));

        if ($declared !== '' && $declared !== 'application/octet-stream') {
            return $declared;
        }

        return MimeTypes::getDefault()->guessMimeType($claimed->path);
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
