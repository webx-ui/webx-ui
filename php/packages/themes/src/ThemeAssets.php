<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Closure;
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
 * A local theme has no `dist/` — the site's own Vite builds its CSS and JS (`ThemeHead`) — but
 * its `assets/` are published the same way, under `public/themes/local-<directory>/`: a picture
 * needs no build, and one path for both kinds of layer is what `theme_asset()` looks up (TH4.2).
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

        $public = rtrim(str_replace('\\', '/', $this->publicPath), '/').'/';

        // Encoded by segment: a picture called «hero image.png» is a file name, not a broken URL.
        $file = implode('/', array_map(rawurlencode(...), explode('/', $file)));

        return $urls->asset(substr($this->directory($layer), strlen($public)).'/'.$current.'/'.$file);
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

        if ($hash === null) {
            return 'nothing';
        }

        return $this->place($layer, $hash, function (string $staging) use ($layer): void {
            foreach ($this->sources($layer) as $relative => $source) {
                $this->files->ensureDirectoryExists(dirname($staging.'/'.$relative));
                $this->files->copy($source, $staging.'/'.$relative);
            }
        }, $force);
    }

    /**
     * Put what `$write` makes into the layer's directory for `$hash` and point `current` at it:
     * the copy of a packaged layer, or files made on the spot — the modules' stylesheets, one
     * file of many ({@see ModuleStyles}). `$write` fills an empty staging directory.
     *
     * @param  Closure(string): void  $write
     * @return 'published'|'unchanged'
     */
    public function place(ThemeManifest $layer, string $hash, Closure $write, bool $force = false): string
    {
        $previous = $this->current($layer);
        $target = $this->directory($layer).'/'.$hash;

        if ($previous === $hash && is_dir($target) && ! $force) {
            return 'unchanged';
        }

        // Written next to the target and renamed into place, so a page never links to half a theme.
        $staging = $target.'.tmp';
        $this->files->deleteDirectory($staging);
        $this->files->ensureDirectoryExists($staging);

        $write($staging);

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

    /**
     * The URL of `theme_asset($file)` (§13.2): the file under `assets/` of the highest layer that
     * has it, as sync published it. Null when no layer has it, or when the layer that has it was
     * not synced since — never a lower layer's copy, which would be a different picture.
     */
    public function asset(ThemeChain $chain, string $file, UrlGenerator $urls): ?string
    {
        $source = self::source($chain, $file);

        if ($source === null) {
            return null;
        }

        return $this->url($source['layer'], 'assets/'.$source['path'], $urls);
    }

    /**
     * Which layer answers `theme_asset($file)`: the top one with `assets/<file>`. Null for a path
     * no layer has, and for one that is not a plain path under `assets/` (`..`, a scheme, empty).
     *
     * @return array{layer: ThemeManifest, path: string, file: string}|null
     */
    public static function source(ThemeChain $chain, string $file): ?array
    {
        $path = self::normalise($file);

        if ($path === null) {
            return null;
        }

        foreach ($chain->layers as $layer) {
            if (is_file($layer->path.'/assets/'.$path)) {
                return ['layer' => $layer, 'path' => $path, 'file' => $layer->path.'/assets/'.$path];
            }
        }

        return null;
    }

    /** `img/wave.svg` as written, or null for a path that leaves `assets/` or is not a path. */
    public static function normalise(string $file): ?string
    {
        $path = ltrim(str_replace('\\', '/', trim($file)), '/');

        if ($path === '' || str_contains($path, ':') || str_contains($path, '?') || str_contains($path, '#')) {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path;
    }

    /**
     * `public/themes/<name>` — the vendor/name of the package, as a path; a local layer, whose
     * name is a directory of the site, under `local-<directory>`: one segment, so it never meets
     * a vendor/name, nor the `icons` and `modules` the engine makes.
     */
    public function directory(ThemeManifest $layer): string
    {
        $name = $layer->local ? 'local-'.basename($layer->path) : $layer->name;

        return rtrim(str_replace('\\', '/', $this->publicPath), '/').'/themes/'.$name;
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

        // A local layer's dist/ is not its own: the site's Vite builds into public/build.
        $directories = $layer->local ? ['assets' => 'assets/'] : ['dist' => '', 'assets' => 'assets/'];

        foreach ($directories as $directory => $prefix) {
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
