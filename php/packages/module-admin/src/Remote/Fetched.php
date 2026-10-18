<?php

declare(strict_types=1);

namespace WebxUi\Admin\Remote;

/**
 * What a fetch brought back: the bytes (empty for a download into a file, and for `HEAD`), the
 * type the server claimed (parameters stripped), the address they finally came from after
 * redirects, the status, and how many bytes there were.
 */
final class Fetched
{
    public function __construct(
        public readonly string $body,
        public readonly ?string $contentType,
        public readonly string $url,
        public readonly int $status = 200,
        public readonly int $size = 0,
        public readonly ?string $path = null,
    ) {}
}
