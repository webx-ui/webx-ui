<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\View\FileViewFinder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebxUi\Themes\Blocks\LayerBlocks;

/**
 * What the chain of this site gives, and from where (spec §16) — the answer to rule 2, «read the
 * chain before writing»: the layers, the tokens with the layer or preset that set each one, every
 * view of the layers with what serves it and what it hides, the block types, icons and fonts of
 * the layers, and the stylesheets of the modules below. `webx:theme:show` prints it.
 *
 * Read from the files, never from the database: the look of a site is its theme, in git
 * (§2, decision 12), so this is the whole of it.
 */
final readonly class ThemeReport
{
    public function __construct(
        private Application $app,
        private ThemeChain $chain,
        private Tokens $tokens,
        private ThemeIcons $icons,
        private ModuleStyles $modules,
        private BottomLayers $bottom,
    ) {}

    /**
     * @return array{
     *     layers: list<array{name: string, title: string, local: bool, path: string, uses: list<string>, preset: string|null}>,
     *     below: list<string>,
     *     preset: string|null,
     *     presets: array<string, array{title: string, tokens: array<string, string>}>,
     *     tokens: array<string, array{type: string, value: string, resolved: string|null, from: string}>,
     *     views: list<array{view: string, from: string, hides: list<string>}>,
     *     blocks: list<array{slug: string, from: string, file: string, hides: list<string>}>,
     *     icons: array<string, string>,
     *     fonts: list<array{family: string, weight: string, style: string, scripts: list<string>, from: string}>,
     *     modules: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'layers' => array_map(fn (ThemeManifest $layer): array => [
                'name' => $layer->name,
                'title' => $layer->title,
                'local' => $layer->local,
                'path' => $this->relative($layer->path),
                'uses' => $layer->uses,
                'preset' => $layer->preset,
            ], $this->chain->layers),
            'below' => array_map(static fn (ThemeManifest $layer): string => $layer->name, $this->bottom->all()),
            'preset' => $this->tokens->preset(),
            'presets' => $this->tokens->presets(),
            'tokens' => $this->tokenRows(),
            'views' => $this->views(),
            'blocks' => $this->blocks(),
            'icons' => array_map(static fn (array $icon): string => $icon['layer'], $this->icons->sources()),
            'fonts' => array_map(static fn (array $face): array => [
                'family' => $face['family'],
                'weight' => $face['weight'],
                'style' => $face['style'],
                'scripts' => array_keys($face['files']),
                'from' => $face['layer']->name,
            ], ThemeFonts::faces($this->chain)),
            'modules' => array_keys($this->modules->sources()),
        ];
    }

    /**
     * @return array<string, array{type: string, value: string, resolved: string|null, from: string}>
     */
    private function tokenRows(): array
    {
        $rows = [];
        $sources = $this->tokens->sources();

        foreach ($this->tokens->values() as $name => $value) {
            $resolved = $this->tokens->resolved($name);

            $rows[$name] = [
                'type' => $this->tokens->vocabulary()->type($name)->value ?? '',
                'value' => $value,
                // Only where it says more than the value: a reference to another token.
                'resolved' => $resolved === $value ? null : $resolved,
                'from' => $sources[$name] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * Every view a layer carries, by its path under `views/`: who serves it — the site's own
     * `resources/views`, a layer, or for `vendor/<ns>/…` the module — and whom it hides.
     *
     * @return list<array{view: string, from: string, hides: list<string>}>
     */
    private function views(): array
    {
        $names = [];

        foreach ($this->chain->layers as $layer) {
            foreach (self::files($layer->views()) as $view) {
                $names[$view] = true;
            }
        }

        ksort($names, SORT_STRING);
        $rows = [];

        foreach (array_keys($names) as $view) {
            $owners = [];

            foreach ($this->sitePaths() as $path) {
                if (is_file($path.'/'.$view)) {
                    $owners[] = 'site';
                }
            }

            foreach ($this->chain->layers as $layer) {
                if ($layer->views() !== null && is_file($layer->views().'/'.$view)) {
                    $owners[] = $layer->name;
                }
            }

            if (preg_match('~^vendor/([^/]+)/(.+)$~', $view, $match) === 1) {
                foreach ($this->modulePaths($match[1]) as $path) {
                    if (is_file($path.'/'.$match[2])) {
                        $owners[] = 'module '.$match[1];
                    }
                }
            }

            $owners = array_values(array_unique($owners));
            $rows[] = ['view' => $view, 'from' => $owners[0], 'hides' => array_slice($owners, 1)];
        }

        return $rows;
    }

    /**
     * The block types of the layers that end up on the site, each with the lower layers whose
     * type of the same slug it overrides.
     *
     * @return list<array{slug: string, from: string, file: string, hides: list<string>}>
     */
    private function blocks(): array
    {
        $rows = [];

        foreach (LayerBlocks::of($this->chain)->set() as $block) {
            $hides = [];

            for ($below = $block->below; $below !== null; $below = $below->below) {
                $hides[] = $below->layer;
            }

            $rows[] = ['slug' => $block->slug, 'from' => $block->layer, 'file' => $this->relative($block->file), 'hides' => $hides];
        }

        return $rows;
    }

    /** @return list<string> */
    private function sitePaths(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $path): ?string => is_string($path) ? rtrim(str_replace('\\', '/', $path), '/') : null,
            (array) $this->app['config']->get('view.paths', []),
        )));
    }

    /**
     * The module's own directories of a namespace: what the view finder holds for it, less the
     * site's `vendor/<ns>` and the layers' overrides.
     *
     * @return list<string>
     */
    private function modulePaths(string $namespace): array
    {
        $finder = $this->app->make('view')->getFinder();

        if (! $finder instanceof FileViewFinder) {
            return [];
        }

        $ours = [
            ...array_map(static fn (string $path): string => $path.'/vendor/'.$namespace, $this->sitePaths()),
            ...$this->chain->namespaceViewPaths($namespace),
        ];

        $paths = [];

        foreach ($finder->getHints()[$namespace] ?? [] as $hint) {
            $hint = rtrim(str_replace('\\', '/', $hint), '/');

            if (! in_array($hint, $ours, true)) {
                $paths[] = $hint;
            }
        }

        return $paths;
    }

    /**
     * The views under a directory, as paths relative to it.
     *
     * @return list<string>
     */
    private static function files(?string $directory): array
    {
        if ($directory === null) {
            return [];
        }

        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = substr(str_replace('\\', '/', $file->getPathname()), strlen($directory) + 1);
            }
        }

        return $files;
    }

    private function relative(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $base = rtrim(str_replace('\\', '/', $this->app->basePath()), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
