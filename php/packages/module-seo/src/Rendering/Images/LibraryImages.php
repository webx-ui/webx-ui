<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering\Images;

use Illuminate\Contracts\Config\Repository as Config;
use Throwable;
use WebxUi\Media\Images\Thumbnails;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Media\Storage\FileUrls;
use WebxUi\Seo\Contracts\SharesImages;

/**
 * A picture of the media library, described by the library's own record: its type, its size,
 * and — for a landscape photo big enough — a variant cut to the size networks recommend.
 *
 * Bound only when `webx-ui/module-media` is installed. What every source hands over is an
 * address (an entity's cover, the default image), so the record is found by taking the disk's
 * own address off the front of it; a picture the library does not hold is described by its
 * address alone ({@see ImagesByAddress}).
 *
 * The variant is 1200×630 by default (`webx-seo.og.image`), cut once and served from the disk
 * like every other variant. Only for a landscape picture at least that big: a portrait photo or
 * a wide logo cut to 1.91:1 loses what it is a picture of, and a small one would be blown up.
 */
final class LibraryImages implements SharesImages
{
    public function __construct(
        private readonly MediaFiles $files,
        private readonly FileStore $store,
        private readonly FileUrls $urls,
        private readonly Thumbnails $thumbnails,
        private readonly ImagesByAddress $byAddress,
        private readonly Config $config,
    ) {}

    public function share(string $url): ?SharedImage
    {
        $file = $this->file($url);

        if (! $file instanceof MediaFile) {
            return $this->byAddress->share($url);
        }

        if (! $file->isImage() || SharedImage::refused($file->mime, $file->path)) {
            return null;
        }

        return $this->variant($file) ?? new SharedImage($url, $file->mime, $file->width, $file->height);
    }

    private function variant(MediaFile $file): ?SharedImage
    {
        $width = (int) $this->config->get('webx-seo.og.image.width', 1200);
        $height = (int) $this->config->get('webx-seo.og.image.height', 630);

        if ($width <= 0 || $height <= 0 || $file->width === null || $file->height === null) {
            return null;
        }

        $landscape = $file->width >= $file->height && $file->width <= $file->height * 2.5;

        if (! $landscape || $file->width < $width || $file->height < $height) {
            return null;
        }

        try {
            $path = $this->thumbnails->variant($file, $width, $height, 'cover');
        } catch (Throwable) {
            // A picture that cannot be cut is shared whole rather than not at all.
            return null;
        }

        $url = $this->urls->variantUrl($file, $path);

        // The variant is written in a format the encoder has, which is not always the original's.
        return new SharedImage($url, $this->byAddress->share($url)->type ?? $file->mime, $width, $height);
    }

    /** The library's record behind an address of its disk, or null for anything else. */
    private function file(string $url): ?MediaFile
    {
        try {
            $marker = '__webx_seo__';
            $base = (string) parse_url($this->store->disk()->url($marker), PHP_URL_PATH);
            $base = substr($base, 0, -strlen($marker));
        } catch (Throwable) {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($base === '' || ! str_starts_with($path, $base)) {
            return null;
        }

        $key = rawurldecode(substr($path, strlen($base)));

        return $key === '' ? null : $this->files->find($key);
    }
}
