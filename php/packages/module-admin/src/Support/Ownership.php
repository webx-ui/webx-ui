<?php

declare(strict_types=1);

namespace WebxUi\Admin\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Whose files `storage` holds, and keeping it that way when artisan runs as somebody else.
 *
 * In a container `docker exec` is root, and the web server is not. A command run that way writes
 * root-owned folders into `storage`, and the first thumbnail the site tries to cut inside one is a
 * 500 (`UnableToCreateDirectory`) — on every page with a picture, after a command that said
 * "Restored.". The owner of `storage` itself is taken as the answer to "who is the site": it is
 * what the deploy set up and what the web server writes as.
 *
 * Without POSIX (Windows) there are no owners to keep, and everything here answers "fine".
 */
class Ownership
{
    public function __construct(private readonly string $storage) {}

    public function available(): bool
    {
        return function_exists('posix_geteuid') && function_exists('lchown');
    }

    public function asRoot(): bool
    {
        return $this->available() && $this->euid() === 0;
    }

    /**
     * The owner and group of `storage`, or null when there is nothing to keep.
     *
     * @return array{uid: int, gid: int}|null
     */
    public function owner(): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $stat = @stat($this->storage);

        return $stat === false ? null : ['uid' => (int) $stat['uid'], 'gid' => (int) $stat['gid']];
    }

    public function name(int $uid): string
    {
        $user = function_exists('posix_getpwuid') ? @posix_getpwuid($uid) : false;

        return is_array($user) ? (string) $user['name'] : (string) $uid;
    }

    /**
     * Why this process must not write into `storage`, or null when it may.
     *
     * Root can hand everything back; the owner writes as itself; somebody in the owner's group
     * can leave what it writes group-writable. Anyone else leaves files the site cannot change,
     * and is turned away before the first one.
     */
    public function refusal(): ?string
    {
        $owner = $this->owner();

        if ($owner === null || $this->euid() === 0 || $this->euid() === $owner['uid'] || in_array($owner['gid'], $this->groups(), true)) {
            return null;
        }

        $name = $this->name($owner['uid']);

        return sprintf(
            'storage belongs to %s, and this runs as %s: the files it writes would be ones the site cannot change. Run it as %s, e.g. `docker exec -u %s …` or `sudo -u %s php artisan …`.',
            $name,
            $this->name($this->euid()),
            $name,
            $name,
            $name,
        );
    }

    /**
     * Gives everything under `$paths` back to the owner of `storage` — the folders a command
     * created, the files it unpacked, a link it made. Links are changed, not followed.
     *
     * As root that is `chown`; as a member of the owner's group it is the group and its write
     * bit on what this process owns. Returns how many paths changed.
     *
     * @param  list<string>  $paths
     */
    public function adopt(array $paths): int
    {
        $owner = $this->owner();

        if ($owner === null || $this->euid() === $owner['uid']) {
            return 0;
        }

        $root = $this->euid() === 0;
        $changed = 0;

        foreach (self::outermost($paths) as $path) {
            foreach (self::walk($path) as $entry) {
                $stat = @lstat($entry);

                if ($stat === false) {
                    continue;
                }

                if ($root) {
                    if ((int) $stat['uid'] !== $owner['uid'] || (int) $stat['gid'] !== $owner['gid']) {
                        $this->change($entry, $owner['uid'], $owner['gid']);
                        $changed++;
                    }

                    continue;
                }

                if ((int) $stat['uid'] === $this->euid() && ! is_link($entry) && ((int) $stat['gid'] !== $owner['gid'] || ($stat['mode'] & 0o020) === 0)) {
                    @chgrp($entry, $owner['gid']);
                    @chmod($entry, ($stat['mode'] & 0o7777) | 0o020);
                    $changed++;
                }
            }
        }

        return $changed;
    }

    /**
     * Paths under `$path` not owned by the owner of `storage`, at most `$limit` of them, and how
     * many there are in all.
     *
     * @return array{count: int, sample: list<string>}
     */
    public function strangers(string $path, int $limit = 5): array
    {
        $owner = $this->owner();
        $found = ['count' => 0, 'sample' => []];

        if ($owner === null) {
            return $found;
        }

        foreach (self::walk($path) as $entry) {
            $stat = @lstat($entry);

            if ($stat !== false && (int) $stat['uid'] !== $owner['uid']) {
                $found['count']++;

                if (count($found['sample']) < $limit) {
                    $found['sample'][] = $entry;
                }
            }
        }

        return $found;
    }

    /**
     * Whether `$uid` (with its groups) may create files in the folder at `$path`, from the mode
     * bits alone: what `is_writable` would answer were it asked as that user rather than as us.
     */
    public function writableBy(string $path, int $uid): bool
    {
        $stat = @stat($path);

        if ($stat === false) {
            return false;
        }

        return self::allows((int) $stat['mode'], (int) $stat['uid'], (int) $stat['gid'], $uid, $this->groupsOf($uid));
    }

    /**
     * @param  list<int>  $groups
     */
    public static function allows(int $mode, int $fileUid, int $fileGid, int $uid, array $groups): bool
    {
        if ($uid === 0) {
            return true;
        }

        if ($fileUid === $uid) {
            return ($mode & 0o200) !== 0;
        }

        if (in_array($fileGid, $groups, true)) {
            return ($mode & 0o020) !== 0;
        }

        return ($mode & 0o002) !== 0;
    }

    /**
     * The uid of a user name or number, or null when there is no such user here.
     */
    public function uidOf(string $user): ?int
    {
        if (ctype_digit($user)) {
            return (int) $user;
        }

        $found = function_exists('posix_getpwnam') ? @posix_getpwnam($user) : false;

        return is_array($found) ? (int) $found['uid'] : null;
    }

    protected function euid(): int
    {
        return function_exists('posix_geteuid') ? posix_geteuid() : -1;
    }

    /** @return list<int> */
    protected function groups(): array
    {
        if (! function_exists('posix_getgroups')) {
            return [];
        }

        return array_values(array_unique([...array_map(intval(...), posix_getgroups() ?: []), posix_getegid()]));
    }

    /**
     * The primary group of `$uid` and every group that names it as a member.
     *
     * @return list<int>
     */
    protected function groupsOf(int $uid): array
    {
        $user = function_exists('posix_getpwuid') ? @posix_getpwuid($uid) : false;

        if (! is_array($user)) {
            return [];
        }

        $groups = [(int) $user['gid']];

        // Secondary groups are only listed in the group database; walking it is what `id` does.
        if (is_readable('/etc/group')) {
            foreach (file('/etc/group', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                $parts = explode(':', $line);

                if (count($parts) === 4 && in_array($user['name'], explode(',', $parts[3]), true)) {
                    $groups[] = (int) $parts[2];
                }
            }
        }

        return array_values(array_unique($groups));
    }

    protected function change(string $path, int $uid, int $gid): void
    {
        @lchown($path, $uid);
        @lchgrp($path, $gid);
    }

    /**
     * The path itself, then everything under it, without following links: a `storage` link in
     * `public` is changed, what it points at is reached through its own walk.
     *
     * @return iterable<string>
     */
    private static function walk(string $path): iterable
    {
        if (! file_exists($path) && ! is_link($path)) {
            return;
        }

        yield $path;

        if (is_link($path) || ! is_dir($path)) {
            return;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            /** @var SplFileInfo $item */
            foreach ($iterator as $item) {
                yield $item->getPathname();
            }
        } catch (UnexpectedValueException) {
            // A folder this process cannot read: nothing in it was written by this process either.
        }
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private static function outermost(array $paths): array
    {
        $paths = array_values(array_unique(array_map(static fn (string $path): string => rtrim(str_replace('\\', '/', $path), '/'), $paths)));

        return array_values(array_filter($paths, static function (string $path) use ($paths): bool {
            foreach ($paths as $other) {
                if ($other !== $path && str_starts_with($path, $other.'/') && ! is_link($path)) {
                    return false;
                }
            }

            return true;
        }));
    }
}
