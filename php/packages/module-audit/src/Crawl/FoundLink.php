<?php

declare(strict_types=1);

namespace WebxUi\Audit\Crawl;

/**
 * One address in a page, made absolute: where it leads, what kind of thing points there, and
 * whether the page wrote it with a host or as a path.
 */
final readonly class FoundLink
{
    public function __construct(
        public string $url,
        public string $kind,
        public ?string $anchor = null,
        public ?string $rel = null,
        public ?string $target = null,
        public bool $absolute = false,
    ) {}
}
