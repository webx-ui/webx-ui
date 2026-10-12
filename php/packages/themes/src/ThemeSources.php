<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * What a packaged theme's `dist/` was built from (spec §4): every file under `src/` by sha256,
 * line endings normalised, so a Windows checkout and CI agree on what "built from" means.
 *
 * A packaged theme commits `dist/` — a site installs it from Composer and has no Node to build
 * it with — and its build writes `dist/sources.json` beside the CSS. The same walk here lets a
 * test see that `src/` changed and `dist/` did not, and lets `webx:theme:make` write the
 * `sources.json` of a theme whose `src/` is only the stubs, so its test is green before anyone
 * has run a build. The build's half is `vite.config.js` of the theme (stubs/package).
 */
final class ThemeSources
{
    /**
     * @return array<string, string> Path under the theme (`src/css/theme.css`) to its hash, sorted.
     */
    public static function of(string $path): array
    {
        $root = rtrim(str_replace('\\', '/', $path), '/');
        $files = [];

        if (! is_dir($root.'/src')) {
            return [];
        }

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);
            $files[$relative] = hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($file->getPathname())));
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    /** `dist/sources.json` byte for byte as the build writes it, two-space indent included. */
    public static function manifest(string $path): string
    {
        $json = (string) json_encode(['algorithm' => 'sha256', 'files' => self::of($path) ?: new \stdClass], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return preg_replace_callback('/^( +)/m', static fn (array $indent): string => str_repeat(' ', intdiv(strlen($indent[1]), 2)), $json)."\n";
    }

    /**
     * Why the packaged theme's `dist/` is not what its `src/` builds into, or null when it is.
     * A theme without `src/` has nothing to build and is never stale.
     */
    public static function stale(string $path): ?string
    {
        $root = rtrim(str_replace('\\', '/', $path), '/');
        $sources = self::of($root);

        if ($sources === []) {
            return null;
        }

        if (! is_file($root.'/dist/theme.css') || ! is_file($root.'/dist/sources.json')) {
            return 'dist/ is not built: run `npm run build` in the theme and commit dist/.';
        }

        $built = json_decode((string) file_get_contents($root.'/dist/sources.json'), true);

        if (! is_array($built) || ($built['files'] ?? null) !== $sources) {
            return 'src/ changed since dist/ was built: run `npm run build` in the theme and commit dist/.';
        }

        return null;
    }
}
