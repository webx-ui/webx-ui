<?php

declare(strict_types=1);

namespace WebxUi\Media\Remote;

/**
 * What a fetch brought back: the bytes, the type the server claimed (parameters stripped), and
 * the address they finally came from after redirects.
 */
final class Fetched
{
    public function __construct(
        public readonly string $body,
        public readonly ?string $contentType,
        public readonly string $url,
    ) {}
}
