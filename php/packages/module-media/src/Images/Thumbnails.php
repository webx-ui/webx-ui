<?php

declare(strict_types=1);

namespace WebxUi\Media\Images;

use Illuminate\Contracts\Config\Repository as Config;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;

/**
 * Variants of an image, cut when they are first asked for and kept on the same disk.
 *
 * Cut on demand rather than on upload: adding a size later would otherwise mean reprocessing
 * the whole library, and most pictures are never asked for in most sizes.
 */
final class Thumbnails
{
    public function __construct(
        private readonly FileStore $files,
        private readonly Config $config,
    ) {}

    /**
     * The key of the variant, cutting it first if it is not there yet.
     */
    public function variant(MediaFile $file, int $width, ?int $height, string $fit): string
    {
        $path = $this->key($file, $width, $height, $fit);
        $disk = $this->files->disk($file->disk);

        if ($disk->exists($path)) {
            return $path;
        }

        $contents = $disk->get($file->path);

        if ($contents === null) {
            throw new MissingSource($file->path);
        }

        $image = $this->manager()->read($contents);

        if ($height === null) {
            $image->scaleDown(width: $width);
        } elseif ($fit === 'contain') {
            $image->scaleDown(width: $width, height: $height);
        } else {
            $image->cover($width, $height);
        }

        $quality = (int) $this->config->get('webx-media.image.quality', 85);

        $disk->put($path, (string) $image->encodeByExtension($this->extension($file), quality: $quality));

        return $path;
    }

    /** Every variant of one file, which is what an edit invalidates. */
    public function forget(MediaFile $file): void
    {
        $this->files->disk($file->disk)->deleteDirectory($this->directory($file));
    }

    private function key(MediaFile $file, int $width, ?int $height, string $fit): string
    {
        $size = $height === null ? (string) $width : "{$width}x{$height}-{$fit}";

        return $this->directory($file)."/{$size}.".$this->extension($file);
    }

    private function directory(MediaFile $file): string
    {
        $prefix = trim((string) $this->config->get('webx-media.prefix', 'media'), '/');

        return "{$prefix}/thumbs/".pathinfo($file->path, PATHINFO_FILENAME);
    }

    /** Formats the encoder does not write come back as the format everything can read. */
    private function extension(MediaFile $file): string
    {
        return in_array($file->extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)
            ? $file->extension
            : 'jpg';
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
