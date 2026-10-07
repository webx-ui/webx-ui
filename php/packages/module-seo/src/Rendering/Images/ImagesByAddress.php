<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering\Images;

use WebxUi\Seo\Contracts\SharesImages;

/**
 * What can be said about a picture from its address alone: its type, by the extension.
 *
 * The answer for a site without the media library, and for a picture the library does not hold
 * — a product photo on the catalogue's own disk. No dimensions: reading the file to learn them
 * would be a request to the disk on every page, and a network measures the picture itself.
 */
final class ImagesByAddress implements SharesImages
{
    private const TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
    ];

    public function share(string $url): ?SharedImage
    {
        if (SharedImage::refused(null, $url)) {
            return null;
        }

        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return new SharedImage($url, self::TYPES[$extension] ?? null);
    }
}
