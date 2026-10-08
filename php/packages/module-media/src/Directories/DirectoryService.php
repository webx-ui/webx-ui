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
