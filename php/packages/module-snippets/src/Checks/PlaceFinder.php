<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Checks;

use Illuminate\Container\Container;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use WebxUi\Compositions\Builder;
use WebxUi\Compositions\Layers;
use WebxUi\Compositions\Patcher;
use WebxUi\Compositions\Reader;

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
 *
 * A layout that is the dispatcher of the composition `layout` (COMPOSITIONS §5.3) prints what
 * the final tree holds: the module adds its places there itself, and a theme's patch may take one
 * away. The tree is built over the themes behind the directories searched.
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

        $layout = $files[self::LAYOUT] ?? null;

        if ($layout !== null && str_contains((string) @file_get_contents($layout), "composition_view('layout'")) {
            foreach ($this->composed($paths) as $place) {
                $places[$place] ??= ['file' => $layout, 'line' => 1];
            }
        }

        return ['places' => $places, 'dynamic' => $dynamic, 'layout' => $layout];
    }

    /**
     * The places of the final tree of `layout`: the themes are the layers whose `views/` are
     * searched, bottom first, the modules those the application loaded.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function composed(array $paths): array
    {
        $container = Container::getInstance();

        if (! $container->bound(Layers::class)) {
            return [];
        }

        $themes = [];

        foreach (array_reverse($paths) as $root) {
            $dir = dirname(rtrim(str_replace('\\', '/', $root), '/')).'/compositions';

            if (is_dir($dir)) {
                $themes[] = ['name' => basename(dirname($dir)), 'dir' => $dir, 'local' => false];
            }
        }

        try {
            $reader = new Reader;
            $tree = (new Builder($container->make(Layers::class)->withThemes(static fn (): array => $themes), $reader, new Patcher($reader)))->build('layout');
        } catch (Throwable) {
            return [];
        }

        $places = [];

        foreach ($tree->root->walk() as $node) {
            if ($node->kind === 'component' && $node->value === 'webx-snippets' && is_string($node->attrs['place'] ?? null)) {
                $places[] = $node->attrs['place'];
            }
        }

        return $places;
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
