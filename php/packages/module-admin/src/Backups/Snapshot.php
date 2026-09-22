<?php

declare(strict_types=1);

namespace WebxUi\Admin\Backups;

use DateTimeImmutable;

/**
 * One file in the backup directory.
 *
 * Nothing about it is recorded anywhere else: what the panel knows about last night is the
 * newest file that is there, and a task that failed is visible as the file that is not (§2.5
 * of the backups spec). A table to hold what the directory already says would be a second
 * thing to keep in step with the first.
 */
final readonly class Snapshot
{
    public function __construct(
        public string $path,
        public DateTimeImmutable $takenAt,
        public int $bytes,
    ) {}

    public function name(): string
    {
        return basename($this->path);
    }
}
