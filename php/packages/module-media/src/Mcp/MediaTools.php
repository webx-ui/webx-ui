<?php

declare(strict_types=1);

namespace WebxUi\Media\Mcp;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;
use Throwable;
use WebxUi\Admin\Uploads\UploadPurposes;
use WebxUi\Admin\Uploads\UploadRefused;
use WebxUi\Admin\Uploads\Uploads;
use WebxUi\Localization\Locales;
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
use WebxUi\Media\Rules\SafeFileType;
use WebxUi\Media\Rules\WithinPixelBudget;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Media\Storage\FileUrls;
use WebxUi\Media\Support\MediaAlt;
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
                'set_alt',
                'Say what is on a picture, per language: the alt every block and page prints for it '
                    .'where the place using it wrote none of its own (an image field\'s own alt wins). '
                    .'Pass a map { "en": "…", "de": "…" } — a language left out keeps what it had, an '
                    .'empty string clears that language; a plain string is the site\'s default language. '
                    .'`null` clears all of it: a decorative picture has no alt.',
                static function (array $arguments): array {
                    $file = self::files([$arguments['id'] ?? null])->first();

                    if (! $file instanceof MediaFile) {
                        throw new ToolFailure('There is no such file.');
                    }

                    if (! array_key_exists('alt', $arguments)) {
                        throw new ToolFailure('Nothing to set: pass `alt`, a map of languages, a string or null.');
                    }

                    $alt = self::alt($file->alt, $arguments['alt']);

                    if ($arguments['dry_run'] ?? false) {
                        return ['ok' => true, 'would' => "set the alt of [{$file->name}]", 'alt' => $alt];
                    }

                    $file->update(['alt' => $alt]);

                    return ['ok' => true, 'id' => $file->id, 'alt' => $file->alt];
                },
                [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'alt' => [
                            'type' => ['object', 'string', 'null'],
                            'additionalProperties' => ['type' => 'string'],
                            'description' => 'Language code => what is on the picture',
                        ],
                    ],
                    'required' => ['id', 'alt'],
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
                'upload_file',
                'Put a file the agent holds into a folder of the library: its bytes as base64 in `content` (a `data:` address is taken too). '
                    .'The same file types, size limit, SVG sanitizing and picture optimizing as an upload in the panel apply; the type is what the bytes are, and the name gets the matching extension. '
                    .'One call carries at most `piece` bytes — about three quarters of what the server lets one request carry; `step: "start"` answers the number, and a call over it is refused with it. '
                    .'A larger one goes in pieces, the way the panel sends every file: `step: "start"` with `name` and `size` answers an `upload` id and the `offset` to send from; '
                    .'`step: "append"` with `upload`, `offset` and the next piece in `content`, as many times as it takes; `step: "finish"` with `upload` and `directory_id` puts it in the library. '
                    .'A start for the same name and size carries on an upload begun earlier; an unfinished one is forgotten after a day. Answers like media_get_file, with `duplicate` when the same bytes were already in that folder.',
                static fn (array $arguments, mixed $user = null): array => self::upload($arguments, $user),
                [
                    'properties' => [
                        'step' => [
                            'type' => 'string',
                            'enum' => ['start', 'append', 'finish'],
                            'description' => 'Only for a file sent in pieces; without it `content` is the whole file',
                        ],
                        'content' => ['type' => 'string', 'description' => 'The file, or this piece of it, as base64'],
                        'name' => ['type' => 'string', 'description' => 'The file name, with its extension'],
                        'directory_id' => ['type' => 'integer'],
                        'alt' => [
                            'type' => ['object', 'string'],
                            'additionalProperties' => ['type' => 'string'],
                            'description' => 'What is on the picture, as for media_set_alt',
                        ],
                        'size' => ['type' => 'integer', 'minimum' => 1, 'description' => 'step start: the whole file, in bytes'],
                        'upload' => ['type' => 'string', 'description' => 'steps append and finish: the id step start answered'],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'description' => 'step append: where this piece starts, the `offset` of the last answer'],
                    ],
                ],
                scope: 'media:write',
                permission: ['media.upload', 'media.manage'],
            ),

            Tool::mutating(
                'optimize_images',
                'Run pictures already in the library through the upload pipeline again: no side longer than the configured limit, metadata stripped, re-encoded at the configured quality. Each keeps its format and its address. With `convert: true` (off by default) JPEG and PNG pictures become WebP instead: a new key with the same uuid, every reference on the site rewritten to it (history included), the old public address redirecting to the new one; a picture is left alone when WebP is not smaller. Without ids, the pictures of the folder (or of the whole library) still to do. At most '.OptimizeController::BATCH.' per call; `remaining` says how many are left. `dry_run` reports per file what would happen, references included, and changes nothing.',
                static function (array $arguments): array {
                    $optimizing = app(LibraryOptimizing::class);
                    $convert = (bool) ($arguments['convert'] ?? false);
                    $query = ($convert ? $optimizing->convertible() : $optimizing->pending())->orderBy('id');

                    if (! empty($arguments['ids'])) {
                        $query->whereIn('id', array_map('intval', (array) $arguments['ids']));
                    } elseif (isset($arguments['directory_id'])) {
                        $query->where('directory_id', (int) $arguments['directory_id']);
                    }

                    $total = (clone $query)->count();

                    if ($arguments['dry_run'] ?? false) {
                        if (! $convert) {
                            return ['ok' => true, 'would' => "optimize {$total} picture(s)"];
                        }

                        $results = $query->limit(OptimizeController::BATCH)->get()
                            ->map(static fn (MediaFile $file): array => $optimizing->convert($file, dryRun: true))->values()->all();

                        return [
                            'ok' => true,
                            'would' => "convert {$total} picture(s); the first ".count($results).' below',
                            'results' => $results,
                        ];
                    }

                    $results = $query->limit(OptimizeController::BATCH)->get()
                        ->map(static fn (MediaFile $file): array => $convert ? $optimizing->convert($file) : $optimizing->run($file))->values()->all();

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
                        'convert' => ['type' => 'boolean', 'description' => 'Convert JPEG and PNG to WebP and rewrite every reference; false by default'],
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

            $requested = trim((string) ($arguments['name'] ?? ''));

            // What the bytes are, not what the server said: a header carries parameters
            // (`image/png; qs=0.7`) and is sometimes simply wrong.
            $file = self::keep(
                $temporary,
                $requested !== '' ? $requested : rawurldecode(basename((string) parse_url($fetched->url, PHP_URL_PATH))),
                $fetched->contentType,
                $directory,
            );
        } finally {
            @unlink($temporary);
        }

        return ['ok' => true, 'id' => $file->id, 'duplicate' => ! $file->wasRecentlyCreated];
    }

    /**
     * Bytes the agent sends itself: the whole file in one call, or a piece at a time through the
     * panel's own uploads (module-admin's {@see Uploads}, purpose `media.library`) — the same
     * sessions, offsets and limits the panel's uploader gets, rather than a second protocol.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function upload(array $arguments, mixed $user): array
    {
        $uploads = app(Uploads::class);
        $dryRun = (bool) ($arguments['dry_run'] ?? false);

        try {
            return match ($arguments['step'] ?? null) {
                null => self::whole($arguments, $dryRun),
                'start' => self::start($uploads, $arguments, $user, $dryRun),
                'append' => self::append($uploads, $arguments, $user, $dryRun),
                'finish' => self::finish($uploads, $arguments, $user, $dryRun),
                default => throw new ToolFailure('`step` is start, append or finish; leave it out to send the whole file in `content`.'),
            };
        } catch (UploadRefused $refused) {
            throw new ToolFailure($refused->offset !== null
                ? $refused->getMessage()." The server has {$refused->offset} byte(s) of it: send the piece that starts at offset {$refused->offset}."
                : $refused->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function whole(array $arguments, bool $dryRun): array
    {
        $directory = self::directory($arguments['directory_id'] ?? null);
        $name = trim((string) ($arguments['name'] ?? ''));

        if ($name === '') {
            throw new ToolFailure('`name` is required: the file name, with its extension.');
        }

        $bytes = self::decoded($arguments['content'] ?? null);
        self::checkAlt($arguments);
        $temporary = self::temporary($bytes);

        try {
            if ($dryRun) {
                // Checked all the same, so a dry run says whether the real call would be refused.
                $mime = self::sniff($temporary) ?? 'application/octet-stream';
                $named = self::named($name, $mime);
                self::validate(new UploadedFile($temporary, $named, $mime, test: true));

                return ['ok' => true, 'would' => "upload [{$named}], ".strlen($bytes)." byte(s), into [{$directory->title}]"];
            }

            $file = self::keep($temporary, $name, null, $directory);
        } finally {
            @unlink($temporary);
        }

        return self::kept($file, $arguments);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function start(Uploads $uploads, array $arguments, mixed $user, bool $dryRun): array
    {
        $name = trim((string) ($arguments['name'] ?? ''));
        $size = (int) ($arguments['size'] ?? 0);

        if ($name === '' || $size < 1) {
            throw new ToolFailure('step start needs `name` (with its extension) and `size`, the whole file in bytes.');
        }

        if ($dryRun) {
            $rules = app(UploadPurposes::class)->find(FileStore::UPLOAD_PURPOSE);

            if ($rules !== null && ! $rules->accepts('', $name)) {
                throw UploadRefused::wrongType($rules->extensions);
            }

            if ($rules?->maxBytes !== null && $size > $rules->maxBytes) {
                throw UploadRefused::tooLarge($rules->maxBytes);
            }

            return ['ok' => true, 'would' => "start an upload of [{$name}], {$size} byte(s)", 'piece' => self::piece($uploads)];
        }

        // The name and the size are what tells a second start of the same file from a new one,
        // so an agent that lost the id carries on from where the server is rather than again.
        [$upload, $created] = $uploads->start($user, FileStore::UPLOAD_PURPOSE, $name, $size, '', "mcp:{$name}:{$size}");

        return [
            'ok' => true,
            'upload' => $upload->id,
            'offset' => $upload->offset,
            'size' => $upload->size,
            'piece' => self::piece($uploads),
            'resumed' => ! $created,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function append(Uploads $uploads, array $arguments, mixed $user, bool $dryRun): array
    {
        if (! isset($arguments['offset']) || ! is_numeric($arguments['offset'])) {
            throw new ToolFailure('step append needs `offset`: where this piece starts, the `offset` of the last answer.');
        }

        $upload = $uploads->find($user, (string) ($arguments['upload'] ?? ''));
        $bytes = self::decoded($arguments['content'] ?? null, self::piece($uploads));
        $offset = (int) $arguments['offset'];

        if ($dryRun) {
            return ['ok' => true, 'would' => 'append '.strlen($bytes)." byte(s) at offset {$offset} of {$upload->size}"];
        }

        $body = fopen('php://memory', 'w+b');

        if ($body === false) {
            throw new ToolFailure('There is no memory to hold the piece in.');
        }

        try {
            fwrite($body, $bytes);
            rewind($body);

            $upload = $uploads->append($upload, $offset, $body, strlen($bytes));
        } finally {
            fclose($body);
        }

        return [
            'ok' => true,
            'upload' => $upload->id,
            'offset' => $upload->offset,
            'size' => $upload->size,
            'remaining' => $upload->size - $upload->offset,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function finish(Uploads $uploads, array $arguments, mixed $user, bool $dryRun): array
    {
        // The folder and the alt before the claim: a claim ends the session, and a refusal
        // after it would cost the agent every piece it sent.
        $directory = self::directory($arguments['directory_id'] ?? null);
        self::checkAlt($arguments);
        $id = (string) ($arguments['upload'] ?? '');

        if ($dryRun) {
            $upload = $uploads->find($user, $id);

            if (! $upload->finished()) {
                throw UploadRefused::unfinished();
            }

            return ['ok' => true, 'would' => "put [{$upload->name}] into [{$directory->title}]"];
        }

        // By whoever is asking, as in the panel: an id another administrator started attaches nothing.
        $claimed = $uploads->claim($id, FileStore::UPLOAD_PURPOSE, $user);

        try {
            $file = self::keep($claimed->path, $claimed->name, null, $directory);
        } finally {
            // Stored or refused, the piece file is done with: the library made its own copy.
            $claimed->discard();
        }

        return self::kept($file, $arguments);
    }

    /**
     * Into the library the way an upload in the panel goes: the type from the bytes, a name that
     * ends in it, the panel's rules, then {@see FileStore::store()} — SVG sanitizing, picture
     * optimizing, the unique key, the duplicate in the same folder.
     */
    private static function keep(string $path, string $name, ?string $declared, MediaDirectory $directory): MediaFile
    {
        $mime = self::sniff($path) ?? $declared ?? 'application/octet-stream';
        $upload = new UploadedFile($path, Str::limit(self::named($name, $mime), 250, ''), $mime, test: true);

        self::validate($upload);

        return app(FileStore::class)->store($upload, $directory);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function kept(MediaFile $file, array $arguments): array
    {
        $duplicate = ! $file->wasRecentlyCreated;

        if (array_key_exists('alt', $arguments)) {
            // Over what a duplicate already had, the way media_set_alt writes it.
            $file->update(['alt' => self::alt($file->alt, $arguments['alt'])]);
        }

        return ['ok' => true, ...self::describe($file), 'duplicate' => $duplicate];
    }

    /**
     * A wrong `alt` refused before anything is stored, rather than after.
     *
     * @param  array<string, mixed>  $arguments
     */
    private static function checkAlt(array $arguments): void
    {
        if (array_key_exists('alt', $arguments)) {
            self::alt(null, $arguments['alt']);
        }
    }

    /**
     * A file's alt with `$given` laid over it — a map keeps the languages it leaves out, a string
     * is the default language, `null` clears all of it.
     *
     * @param  array<string, string>|null  $current
     * @return array<string, string>|null
     */
    private static function alt(?array $current, mixed $given): ?array
    {
        if ($given !== null && ! is_string($given) && ! is_array($given)) {
            throw new ToolFailure('`alt` is a map of languages, a string or null.');
        }

        $locales = app(Locales::class);

        $unknown = is_array($given)
            ? array_values(array_filter(array_keys($given), static fn (int|string $code): bool => ! $locales->has((string) $code)))
            : [];

        if ($unknown !== []) {
            throw new ToolFailure('The site has no language '.implode(', ', $unknown).'. Languages: '.implode(', ', $locales->codes()).'.');
        }

        return match (true) {
            $given === null => null,
            is_string($given) => MediaAlt::clean([...($current ?? []), $locales->defaultCode() => $given]),
            default => MediaAlt::clean([...($current ?? []), ...$given]),
        };
    }

    /**
     * The bytes of base64 `content`, no more than one piece of them: a JSON body several times
     * what PHP lets a request carry never arrives at all, and the agent should hear why first.
     */
    private static function decoded(mixed $content, ?int $limit = null): string
    {
        $content = is_string($content) ? $content : '';

        // A `data:` address is what a model most often holds a picture as.
        if (preg_match('#^data:[^,]*;base64,#i', $content, $prefix) === 1) {
            $content = substr($content, strlen($prefix[0]));
        }

        $bytes = base64_decode((string) preg_replace('#\s+#', '', $content), true);

        if ($bytes === false || $bytes === '') {
            throw new ToolFailure('`content` must be the file as base64, and not empty.');
        }

        $limit ??= self::piece(app(Uploads::class));

        if (strlen($bytes) > $limit) {
            throw new ToolFailure('One call takes at most '.$limit.' byte(s); this is '.strlen($bytes)
                .'. Send it in pieces: `step: "start"` with `name` and `size`, then `step: "append"` a piece at a time, then `step: "finish"`.');
        }

        return $bytes;
    }

    /**
     * The most one call carries: the piece the panel's uploader is advised, less the third that
     * base64 adds — so a call is no larger a request than the panel's own pieces.
     */
    private static function piece(Uploads $uploads): int
    {
        return intdiv($uploads->chunkSize(), 4) * 3;
    }

    private static function temporary(string $bytes): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'webx-media');

        if ($temporary === false || file_put_contents($temporary, $bytes) === false) {
            throw new ToolFailure('There is no temporary file to write the upload into.');
        }

        return $temporary;
    }

    /** The same rules as an upload in the panel: the type white list, the size, the pixel budget. */
    private static function validate(UploadedFile $upload): void
    {
        /** @var list<string> $extensions */
        $extensions = (array) config('webx-media.upload.extensions', []);
        $maxSize = (int) config('webx-media.upload.max_size', 51200);

        $validator = Validator::make(['file' => $upload], [
            'file' => ['required', 'file', 'max:'.$maxSize, 'mimes:'.implode(',', $extensions), new WithinPixelBudget, new SafeFileType],
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
            // Per language, null when nobody described the picture; set with `media_set_alt`.
            'alt' => $file->alt,
            'url' => app(FileUrls::class)->url($file),
        ];
    }
}
