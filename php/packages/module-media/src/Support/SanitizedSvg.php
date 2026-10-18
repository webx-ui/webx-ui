<?php

declare(strict_types=1);

namespace WebxUi\Media\Support;

/** What {@see SvgSanitizer} made of a file, and what it took out to get there. */
final readonly class SanitizedSvg
{
    /**
     * @param  list<string>  $removed  what went, one entry per occurrence: `<script>`, `<rect onclick>`
     */
    public function __construct(
        public string $contents,
        public array $removed,
    ) {}

    public function changed(): bool
    {
        return $this->removed !== [];
    }
}
