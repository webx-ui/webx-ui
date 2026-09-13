<?php

declare(strict_types=1);

namespace WebxUi\Media\Mcp;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use WebxUi\Mcp\Tool;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Media\Storage\FileUrls;
use WebxUi\Media\Support\MediaType;

/**
 * What an agent can do with the library.
 *
 * Deleting a folder is deliberately absent. Recursive deletion is the one operation here that a
 * mistaken call cannot take back, and an agent has no way to ask the question the panel asks
 * before it does it.
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

                    return $file instanceof MediaFile
                        ? self::describe($file)
                        : ['ok' => false, 'reason' => 'No file with that id.'];
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
                    $parent = MediaDirectory::query()->find((int) ($arguments['parent_id'] ?? 0));
                    $title = trim((string) ($arguments['title'] ?? ''));

                    if (! $parent instanceof MediaDirectory) {
                        return ['ok' => false, 'reason' => 'No folder with that id.'];
                    }

                    if ($title === '') {
                        return ['ok' => false, 'reason' => 'A folder needs a name.'];
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
                'rename_file',
                'Change what a file is called. Its address does not change.',
                static function (array $arguments): array {
                    $file = MediaFile::query()->find((int) ($arguments['id'] ?? 0));
                    $name = trim((string) ($arguments['name'] ?? ''));

                    if (! $file instanceof MediaFile || $name === '') {
                        return ['ok' => false, 'reason' => 'A file and a name are both needed.'];
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
                    /** @var list<int> $ids */
                    $ids = array_map('intval', (array) ($arguments['ids'] ?? []));
                    $directory = MediaDirectory::query()->find((int) ($arguments['directory_id'] ?? 0));

                    if (! $directory instanceof MediaDirectory || $ids === []) {
                        return ['ok' => false, 'reason' => 'A folder and at least one file are needed.'];
                    }

                    if ($arguments['dry_run'] ?? false) {
                        return ['ok' => true, 'would' => 'move '.count($ids)." file(s) into [{$directory->title}]"];
                    }

                    $moved = MediaFile::query()
                        ->whereIn('id', $ids)
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
                'Fetch a file from a URL into a folder of the library.',
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
            ),

            Tool::mutating(
                'delete_files',
                'Delete files and the bytes behind them. Folders are not deleted here on purpose.',
                static function (array $arguments): array {
                    /** @var list<int> $ids */
                    $ids = array_map('intval', (array) ($arguments['ids'] ?? []));
                    $files = MediaFile::query()->whereIn('id', $ids)->get();

                    if ($files->isEmpty()) {
                        return ['ok' => false, 'reason' => 'Nothing to delete.'];
                    }

                    if ($arguments['dry_run'] ?? false) {
                        return [
                            'ok' => true,
                            'would' => 'delete '.$files->count().' file(s)',
                            'names' => $files->pluck('name')->all(),
                        ];
                    }

                    return ['ok' => true, 'deleted' => app(FileStore::class)->deleteAll($files)];
                },
                [
                    'properties' => ['ids' => ['type' => 'array', 'items' => ['type' => 'integer']]],
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
        $url = (string) ($arguments['url'] ?? '');
        $directory = MediaDirectory::query()->find((int) ($arguments['directory_id'] ?? 0));

        if (! $directory instanceof MediaDirectory) {
            return ['ok' => false, 'reason' => 'No folder with that id.'];
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return ['ok' => false, 'reason' => 'Only http and https addresses are fetched.'];
        }

        if ($arguments['dry_run'] ?? false) {
            return ['ok' => true, 'would' => "fetch [{$url}] into [{$directory->title}]"];
        }

        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            return ['ok' => false, 'reason' => "The address answered {$response->status()}."];
        }

        $name = (string) ($arguments['name'] ?? basename(parse_url($url, PHP_URL_PATH) ?: 'file'));
        $temporary = tempnam(sys_get_temp_dir(), 'webx-media');

        if ($temporary === false) {
            return ['ok' => false, 'reason' => 'No temporary file to download into.'];
        }

        file_put_contents($temporary, $response->body());

        $file = app(FileStore::class)->store(
            new UploadedFile($temporary, Str::limit($name, 250, ''), $response->header('Content-Type') ?: null, test: true),
            $directory,
        );

        return ['ok' => true, 'id' => $file->id, 'duplicate' => ! $file->wasRecentlyCreated];
    }

    /**
     * @return array<string, mixed>
     */
    private static function describe(MediaFile $file): array
    {
        return [
            'id' => $file->id,
            'directory_id' => $file->directory_id,
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
