<?php

declare(strict_types=1);

namespace WebxUi\Media\Storage;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use WebxUi\Media\Images\Optimizing\ImageOptimizer;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;

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
    public function __construct(
        private readonly FilesystemFactory $filesystems,
        private readonly Config $config,
        private readonly ImageOptimizer $optimizer,
    ) {}

    /**
     * Put an upload into a folder.
     *
     * A picture the pipeline takes goes in as what it made of it ({@see ImageOptimizer}) — a
     * WebP no larger than it needs to be — and everything else as it came. The same bytes
     * already in that folder are the same file: the existing row comes back untouched, and
     * `wasRecentlyCreated` tells the caller which of the two happened.
     */
    public function store(UploadedFile $upload, MediaDirectory $directory): MediaFile
    {
        $extension = $this->extension($upload);
        $mime = (string) $upload->getClientMimeType();

        // A path of the filesystem, deliberately: this is PHP's own temporary file, the one
        // place in the module where there is nothing remote to speak to.
        $optimized = $this->optimizer->handles($extension, $mime)
            ? $this->optimizer->optimize((string) file_get_contents($upload->getRealPath()), $extension, convert: true)
            : null;

        // Of what is kept, not of what arrived, so a second upload of the same photograph finds
        // the first — the pipeline makes the same bytes of it both times.
        $hash = $optimized !== null ? md5($optimized->contents) : (string) md5_file($upload->getRealPath());

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
            [$width, $height] = $this->dimensions($upload);
        }

        $path = $this->key($hash, $extension);
        $disk = $this->disk();

        if ($optimized !== null) {
            $disk->put($path, $optimized->contents);
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
            'size' => $optimized !== null ? strlen($optimized->contents) : (int) $upload->getSize(),
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

    private function extension(UploadedFile $upload): string
    {
        $extension = strtolower($upload->getClientOriginalExtension());

        if ($extension === '') {
            $extension = strtolower((string) $upload->guessExtension());
        }

        return preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(UploadedFile $upload): array
    {
        if (! str_starts_with($upload->getClientMimeType(), 'image/')) {
            return [null, null];
        }

        $size = @getimagesize($upload->getRealPath());

        // An image the platform cannot measure — an SVG, or something claiming to be a picture
        // and not being one — is still a file, and the library is not the place to argue.
        return $size === false ? [null, null] : [(int) $size[0], (int) $size[1]];
    }
}
