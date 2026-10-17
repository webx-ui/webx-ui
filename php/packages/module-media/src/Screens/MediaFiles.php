<?php

declare(strict_types=1);

namespace WebxUi\Media\Screens;

use WebxUi\Media\Models\MediaAlias;
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
     * Keys asked for and not looked up yet: `load()` only notes them, and the first `find()`
     * looks all of them up at once — so two callers loading ahead of the same render (the
     * pictures of a page and its galleries) are one query, not one each.
     *
     * @var array<string, string>
     */
    private array $wanted = [];

    /**
     * Makes sure every one of these keys will be known, in one query with everything else
     * loaded ahead of the next `find()`.
     *
     * @param  list<string>  $paths
     */
    public function load(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path !== '' && ! array_key_exists($path, $this->found)) {
                $this->wanted[$path] = $path;
            }
        }
    }

    public function find(string $path): ?MediaFile
    {
        if (! array_key_exists($path, $this->found)) {
            $this->load([$path]);
            $this->lookUp();
        }

        return $this->found[$path] ?? null;
    }

    /** Everything noted since the last look-up, in one query (and one more for the aliases). */
    private function lookUp(): void
    {
        $missing = $this->wanted;
        $this->wanted = [];

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
            unset($missing[$file->path]);
        }

        // A key a file had before it was converted: everything the site keeps was rewritten, so
        // this is for a value it could not reach — and drawing the picture beats a gap.
        if ($missing !== []) {
            foreach (MediaAlias::query()->whereIn('path', array_values($missing))->with('file')->get() as $alias) {
                $this->found[$alias->path] = $alias->file;
            }
        }
    }

    /** Between two responses of one process the library may well have changed. */
    public function flush(): void
    {
        $this->found = [];
        $this->wanted = [];
    }
}
