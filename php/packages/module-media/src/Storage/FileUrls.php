<?php

declare(strict_types=1);

namespace WebxUi\Media\Storage;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Carbon;
use Throwable;
use WebxUi\Media\Models\MediaFile;

/**
 * The address a file is served from.
 *
 * Computed every time rather than stored: what an entity keeps is the key, so a library that
 * moves from a public directory to S3 does not have to rewrite a single article.
 */
final class FileUrls
{
    public function __construct(
        private readonly FilesystemFactory $filesystems,
        private readonly Config $config,
    ) {}

    public function url(MediaFile $file): string
    {
        return $this->address($file->disk, $file->path, substr($file->hash, 0, 8));
    }

    /** The same, for a variant of that picture: cut from it, so it goes stale with it. */
    public function variantUrl(MediaFile $file, string $path): string
    {
        return $this->address($file->disk, $path, substr($file->hash, 0, 8));
    }

    /**
     * The address of the preview shown instead of the picture itself.
     *
     * `null` for anything that is not an image: there is nothing to cut. The version travels
     * with it for the same reason it travels with the picture — editing writes over the same
     * key, and a preview without it is the picture from before the crop.
     */
    public function thumbUrl(MediaFile $file, int $width = 320, int $height = 320, string $fit = 'cover'): ?string
    {
        if (! $file->isImage()) {
            return null;
        }

        return route('webx.media.files.thumb', [
            'file' => $file->id,
            'w' => $width,
            'h' => $height,
            'fit' => $fit,
            'v' => substr($file->hash, 0, 8),
        ]);
    }

    private function address(string $disk, string $path, string $version): string
    {
        $url = $this->temporary($disk)
            ? $this->signed($disk, $path)
            : $this->filesystems->disk($disk)->url($path);

        // A CDN in front of the disk would otherwise keep serving the picture that used to be
        // under this key: editing an image writes over it rather than making a new one, so that
        // links already in content keep working.
        return $url.(str_contains($url, '?') ? '&' : '?').'v='.$version;
    }

    private function signed(string $disk, string $path): string
    {
        $ttl = (int) $this->config->get('webx-media.temporary_url_ttl', 3600);

        return $this->filesystems->disk($disk)->temporaryUrl($path, Carbon::now()->addSeconds($ttl));
    }

    /**
     * Private buckets get signed addresses, public ones get plain links.
     *
     * `null` means work it out: a disk that names a `url` is meant to be read directly, and so
     * is anything local. Everything else is a bucket nobody promised is public, and guessing
     * wrong there shows the reader an XML error instead of a picture.
     */
    private function temporary(string $disk): bool
    {
        $configured = $this->config->get('webx-media.temporary_urls');

        if (is_bool($configured)) {
            return $configured;
        }

        /** @var array<string, mixed> $settings */
        $settings = (array) $this->config->get("filesystems.disks.{$disk}", []);

        if (($settings['driver'] ?? null) === 'local' || ! empty($settings['url'])) {
            return false;
        }

        return $this->canSign($disk);
    }

    private function canSign(string $disk): bool
    {
        try {
            return method_exists($this->filesystems->disk($disk), 'temporaryUrl');
        } catch (Throwable) {
            return false;
        }
    }
}
