<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Checks;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Where the views print places (§10.1): a static search of the Blade files, without a database,
 * routes or a request — so it works in CI and on a theme checked by its path.
 *
 * Only the file Blade would really read counts: the directories come in lookup order, and a view
 * the site overrides hides the one below it. A layout of the site's own without the places loses
 * them, however many the theme's layout has.
 *
 * Both forms are found: `<x-webx-snippets place="head" />` and theme-default's
 * `<x-dynamic-component component="webx-snippets" place="head" />`. A place set by an expression
 * (`:place="$where"`) cannot be read without rendering — it is reported apart, not guessed.
 */
final class PlaceFinder
{
    private const LAYOUT = 'components/layout.blade.php';

    /**
     * @param  list<string>  $paths  view directories, the first one Blade looks in first
     * @return array{places: array<string, array{file: string, line: int}>, dynamic: list<array{file: string, line: int}>, layout: string|null}
     */
    public function find(array $paths): array
    {
        $places = [];
        $dynamic = [];

        $files = $this->files($paths);

        foreach ($files as $file) {
            $source = @file_get_contents($file);

            if (! is_string($source) || ! str_contains($source, 'webx-snippets')) {
                continue;
            }

            foreach ($this->tags($source) as [$tag, $offset]) {
                $line = substr_count($source, "\n", 0, $offset) + 1;

                if (preg_match('~\splace\s*=\s*"([^"]*)"~', $tag, $match) === 1) {
                    $places[$match[1]] ??= ['file' => $file, 'line' => $line];
                } elseif (preg_match('~\s:place\s*=~', $tag) === 1) {
                    $dynamic[] = ['file' => $file, 'line' => $line];
                }
            }
        }

        return ['places' => $places, 'dynamic' => $dynamic, 'layout' => $files[self::LAYOUT] ?? null];
    }

    /**
     * The component tags of the module in a file, with where each starts.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function tags(string $source): array
    {
        $tags = [];

        preg_match_all('~<x-webx-snippets\b[^>]*>~s', $source, $own, PREG_OFFSET_CAPTURE);

        foreach ($own[0] as $found) {
            $tags[] = [$found[0], $found[1]];
        }

        preg_match_all('~<x-dynamic-component\b[^>]*>~s', $source, $dynamic, PREG_OFFSET_CAPTURE);

        foreach ($dynamic[0] as $found) {
            if (preg_match('~\scomponent\s*=\s*"webx-snippets"~', $found[0]) === 1) {
                $tags[] = [$found[0], $found[1]];
            }
        }

        return $tags;
    }

    /**
     * Every Blade file Blade would read, by its path under its directory; the first directory
     * that has a path wins it.
     *
     * @param  list<string>  $paths
     * @return array<string, string>
     */
    private function files(array $paths): array
    {
        $files = [];

        foreach ($paths as $root) {
            $root = rtrim(str_replace('\\', '/', $root), '/');

            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                $path = str_replace('\\', '/', $file->getPathname());

                if (! str_ends_with($path, '.blade.php')) {
                    continue;
                }

                $files[substr($path, strlen($root) + 1)] ??= $path;
            }
        }

        ksort($files);

        return $files;
    }
}
