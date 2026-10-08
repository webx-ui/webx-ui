<?php

declare(strict_types=1);

namespace WebxUi\Media\Directories;

use Illuminate\Database\ConnectionResolverInterface;
use WebxUi\Media\Exceptions\DirectoryNotEmpty;
use WebxUi\Media\Exceptions\RootIsImmutable;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Media\Usage\MediaUsage;

/**
 * Folder operations that are more than one line of Eloquent.
 */
final class DirectoryService
{
    public function __construct(
        private readonly FileStore $files,
        private readonly ConnectionResolverInterface $connection,
        private readonly MediaUsage $usage,
    ) {}

    /**
     * Delete a folder, and with `$force` everything inside it.
     *
     * The files go first, one at a time, because that is what raises the events that erase the
     * bytes. Letting the node's own delete cascade would take the rows — through the nested set
     * for the folders and through the foreign key for their files — and leave every byte on the
     * disk with nothing pointing at it.
     */
    public function delete(MediaDirectory $directory, bool $force = false): void
    {
        if ($directory->isLibraryRoot()) {
            throw new RootIsImmutable;
        }

        $subtree = $this->subtreeIds($directory);
        $files = MediaFile::query()->whereIn('directory_id', $subtree)->count();
        $folders = count($subtree) - 1;

        if (! $force && ($files > 0 || $folders > 0)) {
            throw new DirectoryNotEmpty($files, $folders);
        }

        $this->connection->connection()->transaction(function () use ($directory, $subtree): void {
            $this->files->deleteAll(
                MediaFile::query()->whereIn('directory_id', $subtree)->cursor(),
            );

            $directory->delete();
        });
    }

    /**
     * «Delete only the unused»: the files of the subtree the site does not use go, and so does
     * every folder that is left empty — the folder itself included. A folder that still holds a
     * used file stays, with the folders above it, so nothing on the site loses its picture.
     *
     * @return array{deleted: int, kept: int, directory_kept: bool}
     */
    public function deleteUnused(MediaDirectory $directory): array
    {
        if ($directory->isLibraryRoot()) {
            throw new RootIsImmutable;
        }

        /** @var list<int> $used */
        $used = array_column($this->usage($directory), 'id');
        $subtree = $this->subtreeIds($directory);

        return $this->connection->connection()->transaction(function () use ($directory, $subtree, $used): array {
            $deleted = $this->files->deleteAll(
                MediaFile::query()->whereIn('directory_id', $subtree)->whereNotIn('id', $used)->cursor(),
            );

            // Deepest first, so a folder whose children have just gone is seen empty in turn.
            $folders = MediaDirectory::query()
                ->whereIn($directory->getKeyName(), $subtree)
                ->orderByDesc($directory->getDepthName())
                ->get();

            foreach ($folders as $folder) {
                $empty = ! MediaFile::query()->where('directory_id', $folder->getKey())->exists()
                    && ! MediaDirectory::query()->where('parent_id', $folder->getKey())->exists();

                if ($empty) {
                    $folder->refresh()->delete();
                }
            }

            return [
                'deleted' => $deleted,
                'kept' => count($used),
                'directory_kept' => MediaDirectory::query()->whereKey($directory->getKey())->exists(),
            ];
        });
    }

    /** What is inside, so the panel can say it before asking whether to go ahead. */
    public function contents(MediaDirectory $directory): DirectoryContents
    {
        $subtree = $this->subtreeIds($directory);

        return new DirectoryContents(
            files: MediaFile::query()->whereIn('directory_id', $subtree)->count(),
            directories: count($subtree) - 1,
        );
    }

    /**
     * The files of the whole subtree the site still uses, and where: what deleting the folder
     * would break, said in the one question the panel asks before it does.
     *
     * @return list<array{id: int, name: string, used_in: list<array<string, mixed>>}>
     */
    public function usage(MediaDirectory $directory): array
    {
        $report = [];

        MediaFile::query()
            ->whereIn('directory_id', $this->subtreeIds($directory))
            ->orderBy('id')
            ->chunkById(200, function ($files) use (&$report): void {
                array_push($report, ...$this->usage->report($files));
            });

        return $report;
    }

    /**
     * @return list<int>
     */
    private function subtreeIds(MediaDirectory $directory): array
    {
        return [
            $directory->getKey(),
            ...$directory->descendants()->pluck($directory->getKeyName())->all(),
        ];
    }
}
