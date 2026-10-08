<?php

declare(strict_types=1);

namespace WebxUi\Admin\Snapshots;

use FilesystemIterator;
use Illuminate\Contracts\Config\Repository;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The uploaded files that travel: the public disk, less what is cut from them.
 *
 * Previews live in `thumbs` folders — the library's under `media/thumbs/<key>`, everyone else's
 * beside the picture (`catalog/0/42/thumbs/<name>`) — and are cut again on the first request,
 * so carrying them only makes the archive bigger. A folder of that name anywhere is skipped, and
 * a restore deletes them on the target: a preview of a picture that was just replaced is a
 * preview of the wrong picture.
 */
final class MediaDisk
{
    public function __construct(private readonly Repository $config) {}

    public function disk(): string
    {
        return (string) $this->config->get('webx-admin.snapshot.disk', 'public');
    }

    public function root(): string
    {
        $disk = $this->disk();

        if ($this->config->get("filesystems.disks.{$disk}.driver") !== 'local') {
            throw SnapshotFailed::notALocalDisk($disk);
        }

        return rtrim(str_replace('\\', '/', (string) $this->config->get("filesystems.disks.{$disk}.root")), '/');
    }

    /**
     * Every file that travels, relative to the root, with `/` for a separator.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $root = $this->root();

        if (! is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($root)), '/');

            if ($this->carried($relative)) {
                $files[] = $relative;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Whether a path is one that travels — and so one a mirror may delete.
     */
    public function carried(string $relative): bool
    {
        if (in_array($relative, $this->kept(), true)) {
            return false;
        }

        $segments = explode('/', $relative);
        array_pop($segments);

        return array_intersect($segments, $this->skipped()) === [];
    }

    /**
     * Every `thumbs` folder (or whatever is configured), deepest first.
     *
     * @return list<string> Absolute paths.
     */
    public function previewFolders(): array
    {
        $root = $this->root();

        if (! is_dir($root)) {
            return [];
        }

        $folders = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir() && in_array($file->getFilename(), $this->skipped(), true)) {
                $folders[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        // A `thumbs` inside a `thumbs` goes with its parent.
        $folders = array_values(array_filter($folders, static function (string $folder) use ($folders): bool {
            foreach ($folders as $other) {
                if ($other !== $folder && str_starts_with($folder, $other.'/')) {
                    return false;
                }
            }

            return true;
        }));

        return $folders;
    }

    public static function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }

    /**
     * @return list<string>
     */
    private function skipped(): array
    {
        $skipped = $this->config->get('webx-admin.snapshot.skip_folders', ['thumbs']);

        return array_values(array_map(strval(...), is_array($skipped) ? $skipped : []));
    }

    /**
     * Files at the root that belong to the stand's checkout rather than to its content.
     *
     * @return list<string>
     */
    private function kept(): array
    {
        return ['.gitignore', '.gitkeep'];
    }
}
