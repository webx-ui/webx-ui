<?php

declare(strict_types=1);

namespace WebxUi\Media\Screens;

use WebxUi\Media\Models\MediaFile;

/**
 * The library rows behind the keys a screen stores, looked up once per request.
 *
 * A gallery of ten pictures is one `whereIn` rather than ten queries, and a page that prints the
 * same picture in three blocks asks about it once. What is kept is the row, never the address: a
 * private bucket's address is signed and expires, so it has to be worked out again every time it
 * is asked for.
 */
final class MediaFiles
{
    /**
     * Rows already looked up in this request, by key — including the keys that were not found.
     *
     * @var array<string, MediaFile|null>
     */
    private array $found = [];

    /**
     * Makes sure every one of these keys is known, in one query.
     *
     * @param  list<string>  $paths
     */
    public function load(array $paths): void
    {
        $missing = [];

        foreach ($paths as $path) {
            if ($path !== '' && ! array_key_exists($path, $this->found)) {
                $missing[$path] = $path;
            }
        }

        if ($missing === []) {
            return;
        }

        // Written down as absent first: a key the library no longer has is an answer too, and
        // asking about it again for every block on the page is exactly what is being avoided.
        foreach ($missing as $path) {
            $this->found[$path] = null;
        }

        foreach (MediaFile::query()->whereIn('path', array_values($missing))->get() as $file) {
            $this->found[$file->path] = $file;
        }
    }

    public function find(string $path): ?MediaFile
    {
        $this->load([$path]);

        return $this->found[$path] ?? null;
    }

    /** Between two responses of one process the library may well have changed. */
    public function flush(): void
    {
        $this->found = [];
    }
}
