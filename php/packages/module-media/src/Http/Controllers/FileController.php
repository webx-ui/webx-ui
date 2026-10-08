<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Mime\MimeTypes;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Uploads\ClaimedUpload;
use WebxUi\Admin\Uploads\Uploads;
use WebxUi\Media\Exceptions\FilesInUse;
use WebxUi\Media\Http\Requests\FileDeleteRequest;
use WebxUi\Media\Http\Requests\FileIndexRequest;
use WebxUi\Media\Http\Requests\FileMoveRequest;
use WebxUi\Media\Http\Requests\FileRenameRequest;
use WebxUi\Media\Http\Requests\FileUploadRequest;
use WebxUi\Media\Http\Resources\FileResource;
use WebxUi\Media\Models\MediaAlias;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Media\Support\MediaType;
use WebxUi\Media\Usage\MediaUsage;

final class FileController
{
    public function __construct(
        private readonly FileStore $files,
        private readonly MediaUsage $usage,
    ) {}

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

        // A key the file had before a conversion finds it too: a value somewhere the rewrite
        // could not reach still opens the picture it meant.
        $file = MediaAlias::resolve($path);

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

    /**
     * Which of these files the site still uses, and where — asked before a delete, so the
     * question the panel puts has the places in it rather than a refusal after the fact.
     */
    public function usage(FileDeleteRequest $request): JsonResponse
    {
        /** @var list<int> $ids */
        $ids = $request->input('ids', []);

        return ApiResponse::data($this->usage->report(MediaFile::query()->whereIn('id', $ids)->get()));
    }

    /**
     * A file the site still uses is refused unless `force` says to go ahead anyway — the rule
     * `media_delete_files` keeps for an agent, kept for a request too, so a call that skipped
     * the panel's question cannot break a page on its own.
     */
    public function destroyOne(Request $request, MediaFile $file): JsonResponse
    {
        $this->guard(new Collection([$file]), $request->boolean('force'));

        $this->files->deleteAll([$file]);

        return ApiResponse::noContent();
    }

    public function destroy(FileDeleteRequest $request): JsonResponse
    {
        /** @var list<int> $ids */
        $ids = $request->input('ids', []);
        $files = MediaFile::query()->whereIn('id', $ids)->get();

        $this->guard($files, $request->boolean('force'));

        return ApiResponse::data(['deleted' => $this->files->deleteAll($files)]);
    }

    /**
     * @param  Collection<int, MediaFile>  $files
     */
    private function guard(Collection $files, bool $force): void
    {
        if ($force) {
            return;
        }

        $inUse = $this->usage->report($files);

        if ($inUse !== []) {
            throw new FilesInUse($inUse);
        }
    }
}
