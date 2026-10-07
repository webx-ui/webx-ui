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
}
