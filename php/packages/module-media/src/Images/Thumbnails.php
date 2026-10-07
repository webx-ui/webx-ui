<?php

declare(strict_types=1);

namespace WebxUi\Media\Images;

use Illuminate\Contracts\Config\Repository as Config;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\Exceptions\RuntimeException as ImageException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;

/**
 * Variants of an image, cut when they are first asked for and kept on the same disk.
 *
 * Cut on demand rather than on upload: adding a size later would otherwise mean reprocessing
 * the whole library, and most pictures are never asked for in most sizes.
 *
 * Two doors onto the same cutting. A library file is named by its row; a picture that is not in
 * the library at all — a product photo, which a catalogue keeps on a disk of its own — is named
 * by where it lies (`variantOf()`). Their variants live in different places for a reason: the
 * library's under its own prefix, where they always were, and anybody else's in a `thumbs`
 * folder beside the picture, so that deleting a product's folder takes its previews with it.
 */
final class Thumbnails
{
    /** Extensions no driver here cuts a variant from. */
    private const UNREADABLE = ['svg', 'svgz', 'heic', 'heif'];

    public function __construct(
        private readonly FileStore $files,
        private readonly Config $config,
    ) {}

    /**
     * The key of the variant of a library file, cutting it first if it is not there yet.
     */
    public function variant(MediaFile $file, int $width, ?int $height, string $fit): string
    {
        return $this->cut($file->disk, $file->path, $this->libraryDirectory($file), $this->format($file->extension), $width, $height, $fit);
    }

    /**
     * The key of the variant of any picture on any disk, cutting it first if it is not there yet.
     */
    public function variantOf(string $disk, string $path, int $width, ?int $height, string $fit): string
    {
        return $this->cut($disk, $path, $this->directoryOf($path), $this->format(pathinfo($path, PATHINFO_EXTENSION)), $width, $height, $fit);
    }

    /** Every variant of one library file, which is what an edit invalidates. */
    public function forget(MediaFile $file): void
    {
        $this->files->disk($file->disk)->deleteDirectory($this->libraryDirectory($file));
    }

    /** Every variant of a picture named by where it lies. */
    public function forgetOf(string $disk, string $path): void
    {
        $this->files->disk($disk)->deleteDirectory($this->directoryOf($path));
    }

    private function cut(?string $diskName, string $source, string $directory, string $extension, int $width, ?int $height, string $fit): string
    {
        $size = $height === null ? (string) $width : "{$width}x{$height}-{$fit}";
        $path = "{$directory}/{$size}.{$extension}";
        $disk = $this->files->disk($diskName);

        if ($disk->exists($path)) {
            return $path;
        }

        // A vector has no smaller copy, and GD reads neither it nor HEIC: said as the decoder's
        // own failure, which every caller already answers with the picture whole.
        if (in_array(strtolower(pathinfo($source, PATHINFO_EXTENSION)), self::UNREADABLE, true)) {
            throw new DecoderException("[{$source}] is not a format a variant can be cut from.");
        }

        $contents = $disk->get($source);

        if ($contents === null) {
            throw new MissingSource($source);
        }

        $image = $this->read($contents, $source);

        if ($height === null) {
            $image->scaleDown(width: $width);
        } elseif ($fit === 'contain') {
            $image->scaleDown(width: $width, height: $height);
        } else {
            $image->cover($width, $height);
        }

        $quality = (int) $this->config->get('webx-media.image.quality', 85);

        $disk->put($path, (string) $image->encodeByExtension($extension, quality: $quality));

        return $path;
    }

    /**
     * The bytes as an image, or a {@see DecoderException}. GD says a format it does not know with
     * a PHP warning before the decoder throws, and with the framework's error handler on — a
     * console command, tinker — the warning was an ErrorException nobody caught: one SVG in a data
     * block's sample stopped every thumbnail of the list.
     */
    private function read(string $contents, string $source): ImageInterface
    {
        try {
            return $this->manager()->read($contents);
        } catch (ImageException $failed) {
            throw $failed;
        } catch (Throwable $failed) {
            throw new DecoderException("[{$source}] could not be read as an image: {$failed->getMessage()}", 0, $failed);
        }
    }

    private function libraryDirectory(MediaFile $file): string
    {
        $prefix = trim((string) $this->config->get('webx-media.prefix', 'media'), '/');

        return "{$prefix}/thumbs/".pathinfo($file->path, PATHINFO_FILENAME);
    }

    /** `catalog/0/42/ab12….jpg` → `catalog/0/42/thumbs/ab12…`. */
    private function directoryOf(string $path): string
    {
        $folder = trim(str_replace('\\', '/', dirname($path)), '/.');
        $name = pathinfo($path, PATHINFO_FILENAME);

        return ltrim(($folder === '' ? '' : $folder.'/')."thumbs/{$name}", '/');
    }

    /** Formats the encoder does not write come back as the format everything can read. */
    private function format(?string $extension): string
    {
        $extension = strtolower((string) $extension);

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $extension : 'jpg';
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
