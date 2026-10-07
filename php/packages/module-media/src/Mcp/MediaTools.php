<?php

declare(strict_types=1);

namespace WebxUi\Media\Mcp;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;
use Throwable;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;
use WebxUi\Media\Directories\DirectoryService;
use WebxUi\Media\Exceptions\DirectoryNotEmpty;
use WebxUi\Media\Http\Controllers\OptimizeController;
use WebxUi\Media\Images\Optimizing\LibraryOptimizing;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Remote\FetchRefused;
use WebxUi\Media\Remote\RemoteFetcher;
use WebxUi\Media\Rules\WithinPixelBudget;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Media\Storage\FileUrls;
use WebxUi\Media\Support\MediaType;
use WebxUi\Media\Usage\MediaUsage;

/**
 * What an agent can do with the library.
 *
 * A refusal is a thrown {@see ToolFailure}, so the agent gets an error result it cannot mistake
 * for success, the way every other module answers.
 *
 * Deleting is guarded twice. A file somewhere on the site — a cover, a block, a setting, a
 * redirect — is not deleted unless the call says `force` ({@see MediaUsage} says where). A
 * folder is deleted only when it is empty: recursive deletion is the one operation a mistaken
 * call cannot take back, and an agent has no way to ask the question the panel asks first.
 */
final class MediaTools
{
    /**
     * @return list<Tool>
     */
    public static function all(): array
    {
        return [
            Tool::read(
                'list_directories',
                'The folders of the media library, as a flat list with their parents and file counts.',
                static fn (): array => MediaDirectory::query()
                    ->withCount('files')
                    ->ordered()
                    ->get()
                    ->map(static fn (MediaDirectory $directory): array => [
                        'id' => $directory->id,
                        'parent_id' => $directory->parent_id,
                        'title' => $directory->title,
                        'depth' => $directory->getDepth(),
                        'files' => $directory->files_count,
                    ])
                    ->all(),
                scope: 'media:read',
            ),

            Tool::read(
                'list_files',
                'Files in the library, newest first. Narrow by folder or by type.',
                static fn (array $arguments): array => self::page($arguments),
                [
                    'properties' => [
                        'directory_id' => ['type' => 'integer', 'description' => 'Only this folder'],
                        'type' => ['type' => 'string', 'enum' => MediaType::all()],
                        'page' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
                scope: 'media:read',
            ),

            Tool::read(
                'search_files',
                'Find files whose name contains the query, across the whole library.',
                static fn (array $arguments): array => self::page($arguments + ['q' => $arguments['query'] ?? '']),
                [
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Part of a name'],
                        'type' => ['type' => 'string', 'enum' => MediaType::all()],
                    ],
                    'required' => ['query'],
                ],
                scope: 'media:read',
            ),

            Tool::read(
                'get_file',
                'One file with its measurements and its address.',
                static function (array $arguments): array {
                    $file = MediaFile::query()->find((int) ($arguments['id'] ?? 0));

                    if (! $file instanceof MediaFile) {
                        throw new ToolFailure('There is no file with id '.self::printable($arguments['id'] ?? null).'. media_search_files finds one by name.');
                    }

                    return self::describe($file);
                },
                [
                    'properties' => ['id' => ['type' => 'integer']],
                    'required' => ['id'],
                ],
                scope: 'media:read',
            ),

            Tool::mutating(
                'create_directory',
                'Create a folder inside another one.',
                static function (array $arguments): array {
                    $parent = self::directory($arguments['parent_id'] ?? null);
                    $title = trim((string) ($arguments['title'] ?? ''));

                    if ($title === '') {
                        throw new ToolFailure('A folder needs a name: `title` is empty.');
                    }

                    if ($arguments['dry_run'] ?? false) {
                        return ['ok' => true, 'would' => "create [{$title}] in [{$parent->title}]"];
                    }

                    $directory = new MediaDirectory(['title' => $title]);
                    $directory->appendTo($parent);

                    return ['ok' => true, 'id' => $directory->refresh()->id];
                },
                [
                    'properties' => [
                        'parent_id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                    ],
                    'required' => ['parent_id', 'title'],
                ],
                scope: 'media:write',
            ),

            Tool::mutating(
                'delete_directory',
                'Delete an empty folder. A folder that still holds files or folders is refused: move or delete what is inside first. The root of the library cannot be deleted.',
                static function (array $arguments): array {
                    $directory = self::directory($arguments['id'] ?? null);

                    if ($directory->isLibraryRoot()) {
                        throw new ToolFailure('The root of the library cannot be deleted.');
                    }

                    $contents = app(DirectoryService::class)->contents($directory);

                    if ($contents->files > 0 || $contents->directories > 0) {
                        throw new ToolFailure("The folder [{$directory->title}] is not empty: {$contents->files} file(s) and {$contents->directories} folder(s) inside. Move or delete them first.");
                    }

                    if ($arguments['dry_run'] ?? false) {
                        return ['ok' => true, 'would' => "delete the empty folder [{$directory->title}]"];
                    }

                    try {
                        app(DirectoryService::class)->delete($directory);
                    } catch (DirectoryNotEmpty) {
                        // Something landed in it between the count and the delete.
                        throw new ToolFailure("The folder [{$directory->title}] is not empty any more.");
                    }

                    return ['ok' => true, 'deleted' => $directory->id];
                },
                [
                    'properties' => ['id' => ['type' => 'integer', 'description' => 'The folder']],
                    'required' => ['id'],
                ],
                scope: 'media:write',
            ),

            Tool::mutating(
                'rename_file',
                'Change what a file is called. Its address does not change.',
                static function (array $arguments): array {
                    $file = self::files([$arguments['id'] ?? null])->first();
                    $name = trim((string) ($arguments['name'] ?? ''));

                    if (! $file instanceof MediaFile) {
                        throw new ToolFailure('There is no such file.');
                    }

                    if ($name === '') {
                        throw new ToolFailure('A file needs a name: `name` is empty.');
                    }

                    if ($arguments['dry_run'] ?? false) {
                        return ['ok' => true, 'would' => "rename [{$file->name}] to [{$name}]"];
                    }

                    $file->update(['name' => $name]);

                    return ['ok' => true, 'id' => $file->id, 'name' => $file->name];
                },
                [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'name'],
                ],
                scope: 'media:write',
            ),

            Tool::mutating(
                'move_files',
                'Move files into a folder. Nothing is copied and no address changes.',
                static function (array $arguments): array {
                    $directory = self::directory($arguments['directory_id'] ?? null);
                    $files = self::files((array) ($arguments['ids'] ?? []));

                    if ($arguments['dry_run'] ?? false) {
                        return ['ok' => true, 'would' => 'move '.$files->count()." file(s) into [{$directory->title}]"];
                    }

                    $moved = MediaFile::query()
                        ->whereKey($files->modelKeys())
                        ->update(['directory_id' => $directory->getKey()]);

                    return ['ok' => true, 'moved' => $moved];
                },
                [
                    'properties' => [
                        'ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'directory_id' => ['type' => 'integer'],
                    ],
                    'required' => ['ids', 'directory_id'],
                ],
                scope: 'media:write',
            ),

            Tool::mutating(
                'upload_from_url',
                'Fetch a file from a public http(s) address into a folder of the library. Addresses on a private network are refused; the same file types and size limit as an upload in the panel apply.',
                static fn (array $arguments): array => self::fromUrl($arguments),
                [
                    'properties' => [
                        'url' => ['type' => 'string'],
                        'directory_id' => ['type' => 'integer'],
                        'name' => ['type' => 'string', 'description' => 'What to call it; the file name by default'],
                    ],
                    'required' => ['url', 'directory_id'],
                ],
                scope: 'media:write',
                permission: ['media.upload', 'media.manage'],
            ),

            Tool::mutating(
                'optimize_images',
                'Run pictures already in the library through the upload pipeline again: no side longer than the configured limit, metadata stripped, re-encoded at the configured quality. Each keeps its format and its address. Without ids, the pictures of the folder (or of the whole library) that the current settings have not been through. At most '.OptimizeController::BATCH.' per call; `remaining` says how many are left.',
                static function (array $arguments): array {
                    $optimizing = app(LibraryOptimizing::class);
                    $query = $optimizing->pending()->orderBy('id');

                    if (! empty($arguments['ids'])) {
                        $query->whereIn('id', array_map('intval', (array) $arguments['ids']));
                    } elseif (isset($arguments['directory_id'])) {
                        $query->where('directory_id', (int) $arguments['directory_id']);
                    }

                    $total = (clone $query)->count();

                    if ($arguments['dry_run'] ?? false) {
                        return ['ok' => true, 'would' => "optimize {$total} picture(s)"];
                    }

                    $results = $query->limit(OptimizeController::BATCH)->get()
                        ->map(static fn (MediaFile $file): array => $optimizing->run($file))->values()->all();

                    return [
                        'ok' => true,
                        'results' => $results,
                        'saved_bytes' => array_sum(array_map(static fn (array $one): int => $one['before'] - $one['after'], $results)),
                        'remaining' => max(0, $total - count($results)),
                    ];
                },
                [
                    'properties' => [
                        'ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'directory_id' => ['type' => 'integer'],
                    ],
                ],
                scope: 'media:write',
            ),

            Tool::mutating(
                'delete_files',
                'Delete files and the bytes behind them. A file the site still uses (a cover, a block, a gallery, a setting, a redirect) is refused with where it is used, unless `force` is true. Folders are deleted with media_delete_directory.',
                static function (array $arguments): array {
                    $files = self::files((array) ($arguments['ids'] ?? []));
                    $force = (bool) ($arguments['force'] ?? false);
                    $usage = app(MediaUsage::class)->of($files);
                    $inUse = self::inUse($files, $usage);

                    if ($arguments['dry_run'] ?? false) {
                        return [
                            'ok' => true,
                            'would' => $inUse !== [] && ! $force
                                ? 'refuse: '.count($inUse).' of '.$files->count().' file(s) are in use; nothing would be deleted without `force`'
                                : 'delete '.$files->count().' file(s)',
                            'names' => $files->pluck('name')->all(),
                            'in_use' => $inUse,
                        ];
                    }

                    if ($inUse !== [] && ! $force) {
                        throw new ToolFailure(self::refusal($inUse));
                    }

                    return ['ok' => true, 'deleted' => app(FileStore::class)->deleteAll($files)];
                },
                [
                    'properties' => [
                        'ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'force' => [
                            'type' => 'boolean',
                            'default' => false,
                            'description' => 'Delete files the site still uses; the places that use them will point at a missing file.',
                        ],
                    ],
                    'required' => ['ids'],
                ],
                scope: 'media:write',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function page(array $arguments): array
    {
        $query = MediaFile::query();

        if (! empty($arguments['directory_id'])) {
            $query->where('directory_id', (int) $arguments['directory_id']);
        }

        if (! empty($arguments['q'])) {
            $query->search((string) $arguments['q']);
        }

        if (! empty($arguments['type'])) {
            MediaType::filter($query, (string) $arguments['type']);
        }

        // Paginated even here: an agent asking for "the files" of a library with forty thousand
        // of them should get a page and a number, not a payload nothing can read.
        $files = $query->latest('created_at')->paginate(50, page: max(1, (int) ($arguments['page'] ?? 1)));

        return [
            'total' => $files->total(),
            'page' => $files->currentPage(),
            'pages' => $files->lastPage(),
            'files' => array_map(self::describe(...), $files->items()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function fromUrl(array $arguments): array
    {
        $url = trim((string) ($arguments['url'] ?? ''));
        $directory = self::directory($arguments['directory_id'] ?? null);

        if (preg_match('#^https?://#i', $url) !== 1) {
            throw new ToolFailure('Only http and https addresses are fetched.');
        }

        if ($arguments['dry_run'] ?? false) {
            return ['ok' => true, 'would' => "fetch [{$url}] into [{$directory->title}]"];
        }

        try {
            $fetched = app(RemoteFetcher::class)->fetch($url);
        } catch (FetchRefused $refused) {
            throw new ToolFailure($refused->getMessage());
        }

        $temporary = tempnam(sys_get_temp_dir(), 'webx-media');

        if ($temporary === false) {
            throw new ToolFailure('There is no temporary file to download into.');
        }

        try {
            file_put_contents($temporary, $fetched->body);

            // What the bytes are, not what the server said: a header carries parameters
            // (`image/png; qs=0.7`) and is sometimes simply wrong.
            $mime = self::sniff($temporary) ?? $fetched->contentType ?? 'application/octet-stream';
            $requested = trim((string) ($arguments['name'] ?? ''));
            $name = self::named($requested !== '' ? $requested : rawurldecode(basename((string) parse_url($fetched->url, PHP_URL_PATH))), $mime);
            $upload = new UploadedFile($temporary, Str::limit($name, 250, ''), $mime, test: true);

            self::validate($upload);

            $file = app(FileStore::class)->store($upload, $directory);
        } finally {
            @unlink($temporary);
        }

        return ['ok' => true, 'id' => $file->id, 'duplicate' => ! $file->wasRecentlyCreated];
    }

    /** The same rules as an upload in the panel: the type white list, the size, the pixel budget. */
    private static function validate(UploadedFile $upload): void
    {
        /** @var list<string> $extensions */
        $extensions = (array) config('webx-media.upload.extensions', []);
        $maxSize = (int) config('webx-media.upload.max_size', 51200);

        $validator = Validator::make(['file' => $upload], [
            'file' => ['required', 'file', 'max:'.$maxSize, 'mimes:'.implode(',', $extensions), new WithinPixelBudget],
        ], [
            'file.mimes' => (string) __('webx-media::errors.unsupported-type', ['types' => implode(', ', $extensions)]),
            'file.max' => (string) __('webx-media::errors.file-too-large', ['size' => round($maxSize / 1024)]),
        ]);

        if ($validator->fails()) {
            throw new ToolFailure((string) $validator->errors()->first('file'));
        }
    }

    private static function sniff(string $path): ?string
    {
        try {
            $mime = MimeTypes::getDefault()->guessMimeType($path);
        } catch (Throwable) {
            return null;
        }

        return $mime === null || $mime === 'application/octet-stream' ? null : strtolower($mime);
    }

    /**
     * A name whose extension is one the library takes. The address's own extension wins when it
     * is on the list; otherwise the one the content says — so `photo.php` holding a PNG is kept
     * as `photo.png`, and never as anything the web server would run.
     */
    private static function named(string $name, string $mime): string
    {
        /** @var list<string> $extensions */
        $extensions = array_map('strtolower', (array) config('webx-media.upload.extensions', []));
        $name = $name !== '' && $name !== '.' && $name !== '/' ? $name : 'file';
        $own = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($own !== '' && in_array($own, $extensions, true)) {
            return $name;
        }

        $guessed = MimeTypes::getDefault()->getExtensions($mime)[0] ?? null;
        $stem = $own === '' ? $name : pathinfo($name, PATHINFO_FILENAME);

        return $guessed !== null ? "{$stem}.{$guessed}" : $name;
    }

    private static function directory(mixed $id): MediaDirectory
    {
        $directory = is_numeric($id) ? MediaDirectory::query()->find((int) $id) : null;

        if (! $directory instanceof MediaDirectory) {
            throw new ToolFailure('There is no folder with id '.self::printable($id).'. media_list_directories has them all.');
        }

        return $directory;
    }

    /**
     * Every file asked for, or a refusal naming the ids nothing answers for — half a batch done
     * is harder to reason about than none.
     *
     * @param  array<int|string, mixed>  $ids
     * @return Collection<int, MediaFile>
     */
    private static function files(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));

        if ($ids === []) {
            throw new ToolFailure('`ids` must list at least one file id.');
        }

        $files = MediaFile::query()->whereKey($ids)->get();
        $missing = array_values(array_diff($ids, array_map('intval', $files->modelKeys())));

        if ($missing !== []) {
            throw new ToolFailure('There is no file with id '.implode(', ', $missing).'. Nothing was changed.');
        }

        return $files;
    }

    /**
     * @param  Collection<int, MediaFile>  $files
     * @param  array<int, list<array{table: string, column: string, id: int|string|null}>>  $usage
     * @return list<array{id: int, name: string, used_in: list<array{table: string, column: string, id: int|string|null}>}>
     */
    private static function inUse(Collection $files, array $usage): array
    {
        $inUse = [];

        foreach ($files as $file) {
            if (isset($usage[$file->id])) {
                $inUse[] = ['id' => (int) $file->id, 'name' => (string) $file->name, 'used_in' => $usage[$file->id]];
            }
        }

        return $inUse;
    }

    /**
     * @param  list<array{id: int, name: string, used_in: list<array{table: string, column: string, id: int|string|null}>}>  $inUse
     */
    private static function refusal(array $inUse): string
    {
        $lines = array_map(static fn (array $file): string => "#{$file['id']} [{$file['name']}] is used in ".implode(', ', array_map(
            static fn (array $place): string => $place['table'].($place['id'] !== null ? " #{$place['id']}" : '')." ({$place['column']})",
            $file['used_in'],
        )), $inUse);

        return 'Nothing was deleted: '.count($inUse).' file(s) are still in use. '.implode('; ', $lines)
            .'. Replace them there first, or pass `force: true` to delete anyway — those places will then point at a missing file.';
    }

    private static function printable(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '(none given)';
    }

    /**
     * @return array<string, mixed>
     */
    private static function describe(MediaFile $file): array
    {
        return [
            'id' => $file->id,
            'directory_id' => $file->directory_id,
            // The key, not only the address: a media field of a block or a screen stores
            // `{ path, alt, title }`, so without this an agent can see a file and still have
            // nothing to write into the field it belongs in.
            'path' => $file->path,
            'name' => $file->name,
            'type' => MediaType::of($file->mime),
            'mime' => $file->mime,
            'size' => $file->size,
            'width' => $file->width,
            'height' => $file->height,
            'url' => app(FileUrls::class)->url($file),
        ];
    }
}
