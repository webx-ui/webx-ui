<?php

declare(strict_types=1);

namespace WebxUi\Media\Images\Optimizing;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use WebxUi\Admin\Events\StoredContentRewritten;
use WebxUi\Media\Images\Thumbnails;
use WebxUi\Media\Models\MediaAlias;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Media\Usage\MediaUsage;

/**
 * «Optimize» for pictures already in the library: the upload pipeline again, over the same key.
 *
 * Written over the same key and in the same format, the way the image editor writes, so every
 * page that shows the picture keeps showing it; the hash moves, and with it the `?v=` of every
 * address worked out from now on. Nothing is kept beside it — the point is the space — except
 * the copy the editor already keeps from before a first edit, which stays what it was.
 *
 * {@see convert()} is the other mode: a JPEG, PNG or HEIC becomes a WebP under a new key — the
 * same uuid, the new extension — and every reference to the old key is rewritten first.
 */
final class LibraryOptimizing
{
    public const OPTIMIZED = 'optimized';

    public const CONVERTED = 'converted';

    public const UNCHANGED = 'unchanged';

    public const SKIPPED = 'skipped';

    public const MISSING = 'missing';

    public function __construct(
        private readonly ImageOptimizer $optimizer,
        private readonly FileStore $files,
        private readonly Thumbnails $thumbnails,
        private readonly ConnectionResolverInterface $connection,
        private readonly MediaUsage $usage,
        private readonly Dispatcher $events,
    ) {}

    /**
     * Pictures «Convert to WebP» would take: still JPEG, PNG (and HEIC, where it can be read),
     * whether or not they have been optimized before — the format is the point here.
     *
     * @return Builder<MediaFile>
     */
    public function convertible(): Builder
    {
        return MediaFile::query()
            ->whereIn('extension', $this->optimizer->convertible())
            ->where('mime', 'like', 'image/%');
    }

    /**
     * A picture into the configured format under a new key, and the site with it.
     *
     * In this order, so that a failure anywhere leaves the site as it was:
     *
     * 1. the new bytes go next to the old ones (`<uuid>.webp` beside `<uuid>.jpg`) — nothing
     *    points at them yet, so writing them first breaks nothing;
     * 2. in one transaction: the row takes the new key, the old key is remembered as an alias, and
     *    every reference to the old basename is rewritten — all of them, history included
     *    ({@see MediaUsage::rewrite()}). Anything failing rolls it back and the new bytes go;
     * 3. only then the old bytes and the old previews go, and the rendered caches are let go of.
     *
     * The copy the editor kept from before the first edit stays as it is, in its own format: it
     * is the picture as it arrived, and «Restore original» re-encodes it into the current one.
     *
     * @return array{id: int, status: string, before: int, after: int, references: int, from: string, to: string|null}
     */
    public function convert(MediaFile $file, bool $dryRun = false): array
    {
        $before = $file->size;
        $from = $file->path;
        $result = static fn (string $status, ?int $after = null, int $references = 0, ?string $to = null): array => [
            'id' => $file->id,
            'status' => $status,
            'before' => $before,
            'after' => $after ?? $before,
            'references' => $references,
            'from' => $from,
            'to' => $to,
        ];

        $format = $this->optimizer->format();

        if ($format === null || ! in_array(strtolower($file->extension), $this->optimizer->convertible(), true) || ! $this->optimizer->handles($file->extension, $file->mime)) {
            return $result(self::SKIPPED);
        }

        $disk = $this->files->disk($file->disk);
        $contents = $disk->get($file->path);

        if ($contents === null) {
            return $result(self::MISSING);
        }

        $optimized = $this->optimizer->optimize($contents, $file->extension, convert: true);

        // Animated, too large to decode, not a picture after all.
        if ($optimized === null) {
            return $result(self::SKIPPED);
        }

        // A WebP that is not smaller is not worth rewriting the site for.
        if ($optimized->extension === strtolower($file->extension) || ! $optimized->smaller) {
            return $result(self::UNCHANGED);
        }

        $to = substr($from, 0, (int) strrpos($from, '.')).'.'.$optimized->extension;
        $after = strlen($optimized->contents);
        $renames = [$file->id => [basename($from), basename($to)]];

        if ($dryRun) {
            return $result(self::CONVERTED, $after, $this->usage->rewrite($renames, dryRun: true)[$file->id] ?? 0, $to);
        }

        if ($disk->exists($to)) {
            return $result(self::SKIPPED);
        }

        $disk->put($to, $optimized->contents);

        try {
            $references = $this->connection->connection()->transaction(function () use ($file, $from, $to, $optimized, $renames): int {
                $file->forceFill([
                    'path' => $to,
                    'extension' => $optimized->extension,
                    'mime' => $optimized->mime,
                    'file_name' => pathinfo($file->file_name, PATHINFO_FILENAME).'.'.$optimized->extension,
                    'hash' => md5($optimized->contents),
                    'size' => strlen($optimized->contents),
                    'width' => $optimized->width,
                    'height' => $optimized->height,
                    'optimized' => $this->optimizer->signature(),
                ])->save();

                MediaAlias::query()->updateOrCreate(['path' => $from], ['file_id' => $file->id]);

                return $this->usage->rewrite($renames)[$file->id] ?? 0;
            });
        } catch (Throwable $error) {
            $disk->delete($to);

            throw $error;
        }

        $disk->delete($from);
        $this->thumbnails->forget($file);
        // Every module that caches content lets go of it; the library does not have to know whose.
        $this->events->dispatch(new StoredContentRewritten([basename($from) => basename($to)]));

        return $result(self::CONVERTED, $after, $references, $to);
    }

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
