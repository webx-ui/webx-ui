<?php

declare(strict_types=1);

namespace WebxUi\Media\Images\Optimizing;

/**
 * What a picture became. `smaller` false means the pipeline could not beat the bytes it was
 * given, and `contents` are those bytes, untouched.
 */
final class Optimized
{
    public function __construct(
        public readonly string $contents,
        public readonly string $extension,
        public readonly string $mime,
        public readonly int $width,
        public readonly int $height,
        public readonly bool $smaller,
    ) {}
}
