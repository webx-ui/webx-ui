<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Filesystem\Filesystem;

/**
 * The built files of packaged layers on the public side (spec §13.2).
 *
 * A packaged theme ships `dist/` (built by its CI) and `assets/`; the web server can only serve
 * what is under `public/`, so `webx:theme:sync` copies them to
 *
 *     public/themes/<name>/<hash>/            ← dist/ as it is
 *     public/themes/<name>/<hash>/assets/     ← assets/
 *     public/themes/<name>/current            ← which <hash> pages link to
 *
 * The directory is named by a hash of the content rather than the package version: a theme
 * linked from the monorepo by a path repository keeps its version while its files change, and a
 * path under `/themes/` is cached forever. The previous copy stays for pages served a second
 * before the switch; older ones go.
 *
 * A local theme is not copied: the site's own Vite builds it (`ThemeHead`).
 */
class ThemeAssets
{
    public function __construct(
        private readonly string $publicPath,
        private readonly Filesystem $files = new Filesystem,
    ) {}

    /** The URL of a published file of a packaged layer, or null when sync has not put it there. */
    public function url(ThemeManifest $layer, string $file, UrlGenerator $urls): ?string
    {
        $current = $this->current($layer);

        if ($current === null || ! is_file($this->directory($layer).'/'.$current.'/'.$file)) {
            return null;
        }

        return $urls->asset('themes/'.$layer->name.'/'.$current.'/'.$file);
    }

    /** The hash pages link to now, or null before the first sync. */
    public function current(ThemeManifest $layer): ?string
    {
        $pointer = $this->directory($layer).'/current';
        $current = is_file($pointer) ? trim((string) file_get_contents($pointer)) : '';

        return preg_match('/^[0-9a-f]{12}$/', $current) === 1 ? $current : null;
    }

    /**
     * What the layer has to publish: its hash, or null when it has neither `dist/` nor `assets/`.
     */
    public function hash(ThemeManifest $layer): ?string
    {
        $sources = $this->sources($layer);

        if ($sources === []) {
            return null;
        }

        $context = hash_init('sha1');

        foreach ($sources as $target => $source) {
            hash_update($context, $target."\0".hash_file('sha1', $source)."\0");
        }

        return substr(hash_final($context), 0, 12);
    }

    /**
     * Copy the layer's files to their hashed directory and point `current` at it.
     *
     * @return 'published'|'unchanged'|'nothing'
     */
    public function publish(ThemeManifest $layer, bool $force = false): string
    {
        $hash = $this->hash($layer);

        if ($layer->local || $hash === null) {
            return 'nothing';
        }

        $previous = $this->current($layer);
        $target = $this->directory($layer).'/'.$hash;

        if ($previous === $hash && is_dir($target) && ! $force) {
            return 'unchanged';
        }

        // Copied next to the target and renamed into place, so a page never links to half a theme.
        $staging = $target.'.tmp';
        $this->files->deleteDirectory($staging);

        foreach ($this->sources($layer) as $relative => $source) {
            $this->files->ensureDirectoryExists(dirname($staging.'/'.$relative));
            $this->files->copy($source, $staging.'/'.$relative);
        }

        $this->files->deleteDirectory($target);
        $this->files->move($staging, $target);

        $pointer = $this->directory($layer).'/current';
        $this->files->put($pointer.'.tmp', $hash);
        $this->files->move($pointer.'.tmp', $pointer);

        foreach ($this->files->directories($this->directory($layer)) as $directory) {
            if (! in_array(basename($directory), [$hash, $previous], true)) {
                $this->files->deleteDirectory($directory);
            }
        }

        return 'published';
    }

    /** `public/themes/<name>` — the vendor/name of the package, as a path. */
    public function directory(ThemeManifest $layer): string
    {
        return rtrim(str_replace('\\', '/', $this->publicPath), '/').'/themes/'.$layer->name;
    }

    /**
     * Every file to publish, keyed by its path under the hashed directory, sorted so the hash
     * does not depend on the order the disk lists them in.
     *
     * @return array<string, string>
     */
    private function sources(ThemeManifest $layer): array
    {
        $sources = [];

        foreach (['dist' => '', 'assets' => 'assets/'] as $directory => $prefix) {
            $root = $layer->path.'/'.$directory;

            if (! is_dir($root)) {
                continue;
            }

            foreach ($this->files->allFiles($root) as $file) {
                $sources[$prefix.str_replace('\\', '/', $file->getRelativePathname())] = $file->getPathname();
            }
        }

        ksort($sources, SORT_STRING);

        return $sources;
    }
}
