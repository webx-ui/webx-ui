<?php

declare(strict_types=1);

namespace WebxUi\Media\Images\Optimizing;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use WebxUi\Media\Images\Thumbnails;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;

/**
 * «Optimize» for pictures already in the library: the upload pipeline again, over the same key.
 *
 * Written over the same key and in the same format, the way the image editor writes, so every
 * page that shows the picture keeps showing it; the hash moves, and with it the `?v=` of every
 * address worked out from now on. Nothing is kept beside it — the point is the space — except
 * the copy the editor already keeps from before a first edit, which stays what it was.
 */
final class LibraryOptimizing
{
    public const OPTIMIZED = 'optimized';

    public const UNCHANGED = 'unchanged';

    public const SKIPPED = 'skipped';

    public const MISSING = 'missing';

    public function __construct(
        private readonly ImageOptimizer $optimizer,
        private readonly FileStore $files,
        private readonly Thumbnails $thumbnails,
        private readonly ConnectionResolverInterface $connection,
    ) {}

    /**
     * Pictures the current settings have not been through yet.
     *
     * @return Builder<MediaFile>
     */
    public function pending(): Builder
    {
        return MediaFile::query()
            ->whereIn('extension', ['jpg', 'jpeg', 'png', 'webp'])
            ->where('mime', 'like', 'image/%')
            ->where(fn (Builder $query) => $query->whereNull('optimized')->orWhere('optimized', '!=', $this->optimizer->signature()));
    }

    /**
     * @return array{id: int, status: string, before: int, after: int}
     */
    public function run(MediaFile $file): array
    {
        $before = $file->size;
        $result = fn (string $status): array => ['id' => $file->id, 'status' => $status, 'before' => $before, 'after' => $file->size];

        if (! $this->optimizer->handles($file->extension, $file->mime)) {
            return $result(self::SKIPPED);
        }

        $disk = $this->files->disk($file->disk);
        $contents = $disk->get($file->path);

        if ($contents === null) {
            return $result(self::MISSING);
        }

        $optimized = $this->optimizer->optimize($contents, $file->extension, convert: false);

        if ($optimized === null) {
            return $result(self::SKIPPED);
        }

        $signature = $this->optimizer->signature();

        if (! $optimized->smaller) {
            // Remembered all the same: the next «Optimize» would only get the same answer.
            $file->forceFill(['optimized' => $signature])->save();

            return $result(self::UNCHANGED);
        }

        // The row first, the bytes second, inside one transaction — the order the editor
        // keeps, for the reason it gives.
        $this->connection->connection()->transaction(function () use ($disk, $file, $optimized, $signature): void {
            $file->forceFill([
                'hash' => md5($optimized->contents),
                'size' => strlen($optimized->contents),
                'width' => $optimized->width,
                'height' => $optimized->height,
                'optimized' => $signature,
            ])->save();

            $disk->put($file->path, $optimized->contents);
        });

        $this->thumbnails->forget($file);

        return $result(self::OPTIMIZED);
    }
}
