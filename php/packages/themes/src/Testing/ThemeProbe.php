<?php

declare(strict_types=1);

namespace WebxUi\Themes\Testing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use WebxUi\Compositions\Builder;
use WebxUi\Compositions\Layers;
use WebxUi\Compositions\Node;
use WebxUi\Compositions\Patcher;
use WebxUi\Compositions\Reader;
use WebxUi\Themes\Checks\ChainCheck;
use WebxUi\Themes\Checks\ContrastCheck;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeLocator;
use WebxUi\Themes\ThemeManifest;
use WebxUi\Themes\ThemeSources;
use WebxUi\Themes\Tokens;
use WebxUi\Themes\Vocabulary;

/**
 * What {@see ThemeContract} holds a theme to, as lists of what is wrong — empty when nothing is.
 *
 * Apart from the trait so that the engine's own tests can hold a theme `webx:theme:make`,
 * `extract` or `eject` just wrote to the same contract without running that theme's test.
 */
final class ThemeProbe
{
    /** Text and its background, by token (rule 29) — {@see ContrastCheck::PAIRS}. */
    public const array PAIRS = ContrastCheck::PAIRS;

    private ?ThemeChain $chain = null;

    public function __construct(
        private readonly string $path,
        private readonly Container $app,
    ) {}

    /**
     * The chain with this theme on top: a package directory is told to the locator under its own
     * name — Composer may not know it, in its own repository or freshly written — and a local
     * theme is named by its path, the way `webx:theme:check <path>` reads one.
     */
    public function chain(): ThemeChain
    {
        if ($this->chain !== null) {
            return $this->chain;
        }

        $locator = $this->app->make(ThemeLocator::class);
        $path = rtrim(str_replace('\\', '/', $this->path), '/');

        if (is_file($path.'/composer.json') && ! is_file($path.'/theme.json')) {
            $manifest = ThemeManifest::fromPackage($path);
            $locator->register($manifest->name, $path);

            return $this->chain = ThemeChain::resolve($manifest->name, $locator);
        }

        return $this->chain = ThemeChain::resolve($path, $locator);
    }

    public function theme(): ThemeManifest
    {
        $top = $this->chain()->top();

        assert($top !== null);

        return $top;
    }

    /**
     * The tokens of the vocabulary — the engine's and every layer's own — that no layer gives a
     * value: the page would print a `var()` that resolves to nothing.
     *
     * @return list<string>
     */
    public function unvalued(): array
    {
        $values = (new Tokens($this->chain()))->values();

        return array_values(array_diff(Vocabulary::forChain($this->chain())->names(), array_keys($values)));
    }

    /**
     * What `webx:theme:check` would say about the chain, a line per finding: tokens, preset,
     * stylesheets, block types, icons, assets, words, fonts, letters. The modules and the widgets
     * are not the theme's and are not read; the site's check reads them.
     *
     * @return list<string>
     */
    public function findings(): array
    {
        return array_map(
            static fn (array $finding): string => trim(($finding['file'] === '' ? '' : $finding['file'].($finding['line'] === null ? '' : ':'.$finding['line']).' ').$finding['message']),
            (new ChainCheck($this->chain(), [], [], []))->run(),
        );
    }

    /**
     * Pairs of text and background below WCAG AA (4.5:1), in the defaults, in every preset of
     * the chain merged over them and, when a layer has one, in the dark scheme of each (rule 29,
     * §7.6). Only colours written as hex can be measured; a pair in another notation is left to
     * the eye, not failed.
     *
     * @return list<string>
     */
    public function unreadable(): array
    {
        return ContrastCheck::failures($this->chain());
    }

    /**
     * Rule 21: the layout every public page is printed in — the highest `components/layout` of
     * the chain — prints the tokens and the layers, the page's head and the blocks' styles, and
     * keeps the two regions an editor fills in the panel.
     *
     * @return list<string>
     */
    public function layoutProblems(): array
    {
        $file = null;

        foreach ($this->chain()->viewPaths() as $directory) {
            if (is_file($directory.'/components/layout.blade.php')) {
                $file = $directory.'/components/layout.blade.php';

                break;
            }
        }

        if ($file === null) {
            return ['no layer of the chain has views/components/layout.blade.php — every public page is printed in it'];
        }

        $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($file));

        if (str_contains($source, "composition_view('layout'")) {
            return $this->compositionProblems($file);
        }

        $problems = [];

        foreach (['@webxTheme' => 'the tokens and the stylesheets of the layers', '@webxSeo' => 'the head of the page', '@webxBlocks' => 'the styles of the blocks on the page'] as $directive => $what) {
            if (preg_match('/'.preg_quote($directive, '/').'\b/', $source) !== 1) {
                $problems[] = "{$file}: no {$directive} — {$what} would be missing";
            }
        }

        foreach (['header', 'footer'] as $region) {
            if (preg_match('/<x-webx-blocks::region\b[^>]*\bname="'.$region.'"/', $source) !== 1) {
                $problems[] = "{$file}: no <x-webx-blocks::region name=\"{$region}\"> — the {$region} an editor arranges in the panel would never show";
            }
        }

        return $problems;
    }

    /**
     * The same seams in the composition `layout` (COMPOSITIONS §3.6), when the layout of the
     * chain is its dispatcher: the final tree of `default` over this chain, the modules the test
     * booted with it.
     *
     * @return list<string>
     */
    private function compositionProblems(string $file): array
    {
        $layers = $this->app->make(Layers::class)->withThemes(fn (): array => array_map(static fn (ThemeManifest $layer): array => [
            'name' => $layer->name,
            'dir' => $layer->path.'/compositions',
            'local' => $layer->local,
        ], array_reverse($this->chain()->layers)));

        try {
            $reader = new Reader;
            $tree = (new Builder($layers, $reader, new Patcher($reader)))->build('layout');
        } catch (Throwable $e) {
            return ["{$file}: the composition layout does not build — {$e->getMessage()}"];
        }

        $directives = [];
        $regions = [];
        $walk = function (Node $node) use (&$walk, &$directives, &$regions): void {
            if ($node->kind === 'directive') {
                $directives[] = $node->value;
            } elseif ($node->kind === 'region') {
                $regions[] = $node->value;
            }

            foreach ([...$node->children, ...$node->fallback] as $child) {
                $walk($child);
            }
        };
        $walk($tree->root);

        $problems = array_map(static fn ($finding): string => "{$file}: {$finding}", array_values(array_filter($tree->findings, static fn ($finding): bool => ! $finding->note)));

        foreach (['webxTheme' => 'the tokens and the stylesheets of the layers', 'webxSeo' => 'the head of the page', 'webxBlocks' => 'the styles of the blocks on the page'] as $directive => $what) {
            if (! in_array($directive, $directives, true)) {
                $problems[] = "{$file}: the composition layout has no @{$directive} — {$what} would be missing";
            }
        }

        foreach (['header', 'footer'] as $region) {
            if (! in_array($region, $regions, true)) {
                $problems[] = "{$file}: the composition layout has no region \"{$region}\" — the {$region} an editor arranges in the panel would never show";
            }
        }

        return $problems;
    }

    /**
     * The views of this theme that Blade cannot turn into PHP. A component of a module that is not
     * installed where the test runs (`<x-webx-…::…>`) is not the theme's fault and is skipped; an
     * unknown component of the theme's own is a view that fails on every page that uses it.
     *
     * @return list<string>
     */
    public function uncompiled(): array
    {
        $views = $this->theme()->views();

        if ($views === null) {
            return [];
        }

        $failures = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            try {
                token_get_all(Blade::compileString((string) file_get_contents($path)), TOKEN_PARSE);
            } catch (InvalidArgumentException $e) {
                if (preg_match('/Unable to locate a class or view for component \[[^\]]*::/', $e->getMessage()) !== 1) {
                    $failures[] = "{$path}: {$e->getMessage()}";
                }
            } catch (Throwable $e) {
                $failures[] = "{$path}: {$e->getMessage()}";
            }
        }

        return $failures;
    }

    /** Why a packaged theme's committed `dist/` is not what its `src/` builds into; null for a local theme. */
    public function staleDist(): ?string
    {
        return $this->theme()->local ? null : ThemeSources::stale($this->theme()->path);
    }
}
