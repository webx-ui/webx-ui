<?php

declare(strict_types=1);

namespace WebxUi\Media\Usage;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Media\Models\MediaFile;

/**
 * Where library files are in use, asked before they are deleted.
 *
 * Every source tagged {@see self::TAG} is asked, and their answers are merged per file. The
 * answer is bounded on purpose: a few places per file are enough to refuse and to say why, and
 * the full list is not what anybody deleting a file needs.
 */
final class MediaUsage
{
    public const TAG = 'webx-media.usage-sources';

    /** How many places are kept per file. */
    public const LIMIT = 10;

    public function __construct(private readonly Container $container) {}

    /**
     * @param  Collection<int, MediaFile>  $files
     * @return array<int, list<array{table: string, column: string, id: int|string|null}>> file id → places, only files in use
     */
    public function of(Collection $files): array
    {
        if ($files->isEmpty()) {
            return [];
        }

        $found = [];

        foreach ($this->container->tagged(self::TAG) as $source) {
            if (! $source instanceof UsageSource) {
                continue;
            }

            foreach ($source->find($files) as $place) {
                $places = $found[$place->fileId] ?? [];

                if (count($places) >= self::LIMIT || in_array($place->toArray(), $places, true)) {
                    continue;
                }

                $places[] = $place->toArray();
                $found[$place->fileId] = $places;
            }
        }

        return $found;
    }

    /**
     * The files of `$files` that are in use, each with where, and every place with a label an
     * editor reads ({@see PlaceLabels}) — what the panel shows before it deletes anything.
     *
     * @param  Collection<int, MediaFile>  $files
     * @return list<array{id: int, name: string, used_in: list<array{table: string, column: string, id: int|string|null, label: string|null}>}>
     */
    public function report(Collection $files): array
    {
        $usage = $this->of($files);

        if ($usage === []) {
            return [];
        }

        $labels = new PlaceLabels;
        $report = [];

        foreach ($files as $file) {
            if (! isset($usage[$file->id])) {
                continue;
            }

            $report[] = [
                'id' => (int) $file->id,
                'name' => (string) $file->name,
                'used_in' => array_map(
                    static fn (array $place): array => [...$place, 'label' => $labels->of($place['table'], $place['id'])],
                    $usage[$file->id],
                ),
            ];
        }

        return $report;
    }

    /**
     * Move every reference from each file's old basename to its new one, through every source
     * that can ({@see UsageRewriter}).
     *
     * @param  array<int, array{0: string, 1: string}>  $renames  file id → [old basename, new basename]
     * @return array<int, int> file id → places rewritten, or that would be on a dry run
     */
    public function rewrite(array $renames, bool $dryRun = false): array
    {
        $total = [];

        if ($renames === []) {
            return $total;
        }

        foreach ($this->container->tagged(self::TAG) as $source) {
            if (! $source instanceof UsageRewriter) {
                continue;
            }

            foreach ($source->rewrite($renames, $dryRun) as $fileId => $count) {
                $total[$fileId] = ($total[$fileId] ?? 0) + $count;
            }
        }

        return $total;
    }
}
