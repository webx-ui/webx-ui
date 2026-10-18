<?php

declare(strict_types=1);

namespace WebxUi\Media\Storage;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WebxUi\Media\Images\Optimizing\ImageOptimizer;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Support\FileTypes;
use WebxUi\Media\Support\SvgSanitizer;

/**
 * Everything that puts bytes on a disk or takes them off it.
 *
 * Two rules hold the rest together. A key is built from a uuid, not from the file's name or its
 * hash, so the same picture uploaded into two folders is two keys and deleting one cannot empty
 * the other. And a key says nothing about the folder, so moving a file between folders is one
 * column — which on S3 is the difference between instant and copy-then-delete.
 */
final class FileStore
{
    /**
     * What the library's chunked uploads are registered as with the panel's protocol: every
     * upload into Files — the page, the picker, a field — goes a piece at a time under it.
     */
    public const UPLOAD_PURPOSE = 'media.library';

    public function __construct(
        private readonly FilesystemFactory $filesystems,
        private readonly Config $config,
        private readonly ImageOptimizer $optimizer,
        private readonly FileTypes $types,
        private readonly SvgSanitizer $svg,
    ) {}

    /**
     * Put an upload into a folder.
     *
     * A picture the pipeline takes goes in as what it made of it ({@see ImageOptimizer}) — a
     * WebP no larger than it needs to be — and everything else as it came. The same bytes
     * already in that folder are the same file: the existing row comes back untouched, and
     * `wasRecentlyCreated` tells the caller which of the two happened.
     *
     * The extension and the type are the server's, read from the bytes ({@see FileTypes}); the
     * client's are not believed. An SVG is kept as {@see SvgSanitizer} made it.
     *
     * @throws ValidationException when the bytes make no type the library keeps, or an SVG
     *                             that cannot be made safe — the upload rules refuse both
     *                             earlier, this is for a caller that skipped them
     */
    public function store(UploadedFile $upload, MediaDirectory $directory): MediaFile
    {
        [$extension, $mime] = $this->types->resolve($upload)
            ?? throw ValidationException::withMessages(['file' => __('webx-media::errors.type-mismatch')]);

        // A path of the filesystem, deliberately: this is PHP's own temporary file, the one
        // place in the module where there is nothing remote to speak to.
        $sanitized = $mime === FileTypes::SVG ? $this->sanitized($upload) : null;

        $optimized = $this->optimizer->handles($extension, $mime)
            ? $this->optimizer->optimize((string) file_get_contents($upload->getRealPath()), $extension, convert: true)
            : null;

        // Of what is kept, not of what arrived, so a second upload of the same photograph finds
        // the first — the pipeline makes the same bytes of it both times.
        $kept = $optimized->contents ?? $sanitized;
        $hash = $kept !== null ? md5($kept) : (string) md5_file($upload->getRealPath());

        $existing = MediaFile::query()
            ->where('directory_id', $directory->getKey())
            ->where('hash', $hash)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $original = $upload->getClientOriginalName();

        if ($optimized !== null) {
            [$extension, $mime, $width, $height] = [$optimized->extension, $optimized->mime, $optimized->width, $optimized->height];
        } else {
            [$width, $height] = $this->dimensions($upload, $mime);
        }

        $path = $this->key($hash, $extension);
        $disk = $this->disk();

        if ($kept !== null) {
            $disk->put($path, $kept);
        } else {
            $disk->putFileAs(dirname($path), $upload, basename($path));
        }

        return MediaFile::query()->create([
            'directory_id' => $directory->getKey(),
            'disk' => $this->diskName(),
            'path' => $path,
            'hash' => $hash,
            // Without the extension: it is on screen in a grid of names, and the type is already
            // said by the preview and the glyph.
            'name' => Str::limit(pathinfo($original, PATHINFO_FILENAME), 200, ''),
            // A download of a converted picture is named for what it now is.
            'file_name' => Str::limit($this->named($original, $extension), 250, ''),
            'extension' => $extension,
            'mime' => $mime,
            'size' => $kept !== null ? strlen($kept) : (int) $upload->getSize(),
            'width' => $width,
            'height' => $height,
            'optimized' => $optimized !== null ? $this->optimizer->signature() : null,
        ]);
    }

    /** The same bytes under a new key, in the same folder, as a separate file. */
    public function copy(MediaFile $file, string $name): MediaFile
    {
        $path = $this->key($file->hash, $file->extension);

        $this->disk($file->disk)->copy($file->path, $path);

        return MediaFile::query()->create([
            ...$file->only([
                'directory_id',
                'disk',
                'hash',
                'file_name',
                'extension',
                'mime',
                'size',
                'width',
                'height',
            ]),
            'path' => $path,
            'name' => $name,
        ]);
    }

    /**
     * Delete files and the bytes behind them.
     *
     * One at a time through Eloquent on purpose: a mass delete raises no model events, and the
     * bytes would stay on the disk with nothing left pointing at them.
     *
     * @param  iterable<int, MediaFile>  $files
     */
    public function deleteAll(iterable $files): int
    {
        $deleted = 0;

        foreach ($files as $file) {
            $file->delete();
            $deleted++;
        }

        return $deleted;
    }

    public function disk(?string $name = null): Filesystem
    {
        return $this->filesystems->disk($name ?? $this->diskName());
    }

    public function diskName(): string
    {
        return (string) $this->config->get('webx-media.disk', 'public');
    }

    /** `media/9f/2a/018f-….jpg` — sharded so no directory holds tens of thousands of entries. */
    private function key(string $hash, string $extension): string
    {
        $prefix = trim((string) $this->config->get('webx-media.prefix', 'media'), '/');
        $name = (string) Str::uuid();

        return "{$prefix}/".substr($hash, 0, 2).'/'.substr($hash, 2, 2)."/{$name}.{$extension}";
    }

    private function named(string $original, string $extension): string
    {
        $own = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if ($own === $extension || ($own === 'jpeg' && $extension === 'jpg') || ($own === 'jpg' && $extension === 'jpeg')) {
            return $original;
        }

        return pathinfo($original, PATHINFO_FILENAME).'.'.$extension;
    }

    private function sanitized(UploadedFile $upload): string
    {
        $sanitized = $this->svg->sanitize((string) file_get_contents($upload->getRealPath()));

        if ($sanitized === null) {
            throw ValidationException::withMessages(['file' => __('webx-media::errors.unsafe-svg')]);
        }

        return $sanitized->contents;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(UploadedFile $upload, string $mime): array
    {
        if (! str_starts_with($mime, 'image/')) {
            return [null, null];
        }

        $size = @getimagesize($upload->getRealPath());

        // An image the platform cannot measure — an SVG, or something claiming to be a picture
        // and not being one — is still a file, and the library is not the place to argue.
        return $size === false ? [null, null] : [(int) $size[0], (int) $size[1]];
    }
}
