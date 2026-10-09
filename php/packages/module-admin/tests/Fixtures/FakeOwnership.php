<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use WebxUi\Admin\Support\Ownership;

/**
 * Ownership as seen by a process with another uid, on a `storage` that belongs to www-data (33).
 */
final class FakeOwnership extends Ownership
{
    /**
     * @param  list<int>  $groups
     * @param  list<string>  $closed  Folders under storage the web server cannot write.
     */
    public function __construct(
        private readonly string $root,
        private readonly int $euid,
        private readonly array $groups,
        private readonly array $closed = [],
        private readonly int $strangers = 0,
    ) {
        parent::__construct($root);
    }

    public function available(): bool
    {
        return true;
    }

    public function asRoot(): bool
    {
        return $this->euid === 0;
    }

    public function owner(): array
    {
        return ['uid' => 33, 'gid' => 33];
    }

    public function name(int $uid): string
    {
        return match ($uid) {
            0 => 'root',
            33 => 'www-data',
            default => 'user'.$uid,
        };
    }

    public function adopt(array $paths): int
    {
        return 0;
    }

    public function strangers(string $path, int $limit = 5): array
    {
        return ['count' => $this->strangers, 'sample' => $this->strangers > 0 ? [$this->root.'/app/public/media'] : []];
    }

    public function writableBy(string $path, int $uid): bool
    {
        foreach ($this->closed as $relative) {
            if (str_replace('\\', '/', $path) === str_replace('\\', '/', $this->root.'/'.$relative)) {
                return false;
            }
        }

        return true;
    }

    protected function euid(): int
    {
        return $this->euid;
    }

    protected function groups(): array
    {
        return $this->groups;
    }
}
