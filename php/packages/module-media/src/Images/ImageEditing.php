<?php

declare(strict_types=1);

namespace WebxUi\Media\Images;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionResolverInterface;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;

/**
 * Cropping, rotating, flipping and scaling a picture that is already in the library.
 *
 * The result is written over the same key. Every address already in an article keeps working —
 * which is the whole reason the editor exists here rather than as "upload the fixed one".
 */
final class ImageEditing
{
    public function __construct(
        private readonly FileStore $files,
        private readonly Thumbnails $thumbnails,
        private readonly Config $config,
        private readonly ConnectionResolverInterface $connection,
    ) {}

    /**
     * @param  array{crop?: array{x: int, y: int, width: int, height: int}, rotate?: int, flip?: string, resize?: array{width?: int, height?: int}}  $operations
     */
    public function apply(MediaFile $file, array $operations): MediaFile
    {
        $disk = $this->files->disk($file->disk);
        $contents = $disk->get($file->path);

        if ($contents === null) {
            throw new MissingSource($file->path);
        }

        // Once, and before anything is written: after this the original is a copy nobody
        // overwrites, so an edit — and every edit after it — can be undone.
        if (! $file->hasOriginal()) {
            $original = $this->originalKey($file);
            $disk->put($original, $contents);
            $file->original_path = $original;
        }

        $image = $this->edit($this->manager()->read($contents), $operations);

        $encoded = (string) $image->encodeByExtension(
            $file->extension,
            quality: (int) $this->config->get('webx-media.image.quality', 85),
        );

        // The row first, the bytes second, both inside one transaction.
        //
        // The other way round is how a picture and its record stop agreeing: the file is
        // written, the update then fails for a reason that has nothing to do with images, and
        // what is on the disk is a crop the panel goes on describing at its old size.
        $this->connection->connection()->transaction(function () use ($disk, $encoded, $file, $image): void {
            $file->fill([
                'hash' => md5($encoded),
                'size' => strlen($encoded),
                'width' => $image->width(),
                'height' => $image->height(),
            ])->save();

            $disk->put($file->path, $encoded);
        });

        // Cut from what has just changed, so they have to go; the address of the picture itself
        // carries a new version for the same reason.
        $this->thumbnails->forget($file);

        return $file;
    }

    /** Put the copy kept before the first edit back where the picture lives. */
    public function restore(MediaFile $file): MediaFile
    {
        if (! $file->hasOriginal()) {
            return $file;
        }

        $disk = $this->files->disk($file->disk);
        $contents = $disk->get((string) $file->original_path);

        if ($contents === null) {
            throw new MissingSource((string) $file->original_path);
        }

        $size = $this->manager()->read($contents);

        $this->connection->connection()->transaction(function () use ($contents, $disk, $file, $size): void {
            $file->fill([
                'hash' => md5($contents),
                'size' => strlen($contents),
                'width' => $size->width(),
                'height' => $size->height(),
            ])->save();

            $disk->put($file->path, $contents);
        });

        $this->thumbnails->forget($file);

        return $file;
    }

    /**
     * @param  array{crop?: array{x: int, y: int, width: int, height: int}, rotate?: int, flip?: string, resize?: array{width?: int, height?: int}}  $operations
     */
    private function edit(ImageInterface $image, array $operations): ImageInterface
    {
        // Turning comes first, and the crop is read against the turned picture.
        //
        // That is the order the editor works in: its frame is dragged over what is on screen,
        // which is already rotated and flipped. Cropping the original first and turning the
        // result afterwards would take a different rectangle for every angle but zero — the
        // crop would silently land somewhere else the moment somebody pressed rotate.
        if (! empty($operations['rotate'])) {
            // Counter-clockwise in Intervention, clockwise for anyone pressing the button.
            $image->rotate(-(float) $operations['rotate']);
        }

        if (isset($operations['flip'])) {
            $operations['flip'] === 'vertical' ? $image->flip() : $image->flop();
        }

        if (isset($operations['crop'])) {
            $crop = $operations['crop'];
            $image->crop($crop['width'], $crop['height'], $crop['x'], $crop['y']);
        }

        if (isset($operations['resize'])) {
            $image->scaleDown(
                width: $operations['resize']['width'] ?? null,
                height: $operations['resize']['height'] ?? null,
            );
        }

        return $image;
    }

    private function originalKey(MediaFile $file): string
    {
        $prefix = trim((string) $this->config->get('webx-media.prefix', 'media'), '/');

        return "{$prefix}/originals/".pathinfo($file->path, PATHINFO_BASENAME);
    }

    private function manager(): ImageManager
    {
        return new ImageManager(
            $this->config->get('webx-media.image.driver') === 'imagick'
                ? new ImagickDriver
                : new GdDriver
        );
    }
}
