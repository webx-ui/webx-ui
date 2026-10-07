<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering\Images;

/**
 * A picture as `og:image` and its companions print it. Whatever is not known stays null and is
 * not printed — a guessed width is a card cropped wrong.
 */
final class SharedImage
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $type = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
    ) {}

    /** Formats a social network does not take: vector images, and icons. */
    public static function refused(?string $mime, string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return in_array($mime, ['image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'], true)
            || preg_match('~\.(svgz?|ico)$~', $path) === 1;
    }
}
