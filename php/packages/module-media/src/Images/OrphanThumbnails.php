<?php

declare(strict_types=1);

namespace WebxUi\Media\Images;

use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;

/**
 * Folders of previews whose file is gone.
 *
 * Every library file's variants live in `media/thumbs/<the name of its key>`. Until deleting a
 * file took that folder with it, a deleted file left it behind, and nothing else ever looks
 * there — so this is the sweep for what is already on the disk: `webx:media:prune-thumbs`, and
 * the audit's `media.orphan_thumbs` with its fix.
 *
 * A folder is an orphan when no row of the library on that disk has a key of that name. The
 * names are uuids, so a folder is never mistaken for somebody else's.
 */
final class OrphanThumbnails
{
    public function __construct(
        private readonly FileStore $files,
        private readonly Thumbnails $thumbnails,
    ) {}

    /**
     * @return list<array{disk: string, path: string}>
     */
    public function find(): array
    {
        $root = $this->thumbnails->libraryRoot();
        $orphans = [];

        foreach ($this->disks() as $diskName) {
            $folders = $this->files->disk($diskName)->directories($root);

            if ($folders === []) {
                continue;
            }

            $known = [];

            foreach (MediaFile::query()->where('disk', $diskName)->toBase()->select(['id', 'path'])->lazyById(1000, 'id') as $row) {
                $known[pathinfo((string) $row->path, PATHINFO_FILENAME)] = true;
            }

            foreach ($folders as $folder) {
                if (! isset($known[basename($folder)])) {
                    $orphans[] = ['disk' => $diskName, 'path' => $folder];
                }
            }
        }

        return $orphans;
    }

    /**
     * Delete the orphans, found afresh rather than taken from a list a moment old — a file
     * uploaded in between has previews that are not orphans.
     *
     * @return int how many folders went
     */
    public function sweep(): int
    {
        $swept = 0;

        foreach ($this->find() as $orphan) {
            $this->files->disk($orphan['disk'])->deleteDirectory($orphan['path']);
            $swept++;
        }

        return $swept;
    }

    /**
     * The library's disk, and any other its rows say they are on.
     *
     * @return list<string>
     */
    private function disks(): array
    {
        /** @var list<string> $used */
        $used = MediaFile::query()->distinct()->pluck('disk')->map(static fn (mixed $disk): string => (string) $disk)->all();

        return array_values(array_unique([$this->files->diskName(), ...$used]));
    }
}
