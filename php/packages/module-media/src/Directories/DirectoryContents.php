<?php

declare(strict_types=1);

namespace WebxUi\Media\Directories;

/** What a folder holds, counted through its whole subtree. */
final readonly class DirectoryContents
{
    public function __construct(
        public int $files,
        public int $directories,
    ) {}

    public function isEmpty(): bool
    {
        return $this->files === 0 && $this->directories === 0;
    }
}
