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
use WebxUi\Themes\Checks\ChainCheck;
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
    /** Text and its background, by token: body text, quiet text, the accent as text and as a button (rule 29). */
    public const array PAIRS = [
        ['color-text', 'color-bg'],
        ['color-text', 'color-surface'],
        ['color-text-muted', 'color-bg'],
        ['color-text-muted', 'color-surface'],
        ['color-accent-contrast', 'color-accent'],
        ['color-accent', 'color-bg'],
    ];

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
     * Pairs of text and background below WCAG AA (4.5:1), in the defaults and in every preset of
     * the chain merged over them (rule 29). Only colours written as hex can be measured; a pair
     * in another notation is left to the eye, not failed.
     *
     * @return list<string>
     */
    public function unreadable(): array
    {
        $defaults = [];

        foreach (array_reverse($this->chain()->layers) as $layer) {
            $defaults = [...$defaults, ...$layer->tokens()->defaults];
        }

        $palettes = ['defaults' => $defaults];

        foreach ((new Tokens($this->chain()))->presets() as $name => $preset) {
            $palettes["preset {$name}"] = [...$defaults, ...$preset['tokens']];
        }

        $failures = [];

        foreach ($palettes as $palette => $values) {
            foreach (self::PAIRS as [$fore, $back]) {
                $ratio = self::contrast(self::resolve($values, $fore), self::resolve($values, $back));

                if ($ratio !== null && $ratio < 4.5) {
                    $failures[] = sprintf('%s: %s on %s is %.2f:1, below 4.5:1', $palette, $fore, $back, $ratio);
                }
            }
        }

        return $failures;
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

    /** @param array<string, string> $values */
    private static function resolve(array $values, string $name): ?string
    {
        $value = $values[$name] ?? null;

        for ($depth = 0; $depth < 10 && $value !== null && preg_match('/^var\(--site-([a-z0-9-]+)\)$/', $value, $match) === 1; $depth++) {
            $value = $values[$match[1]] ?? null;
        }

        return $value;
    }

    private static function contrast(?string $fore, ?string $back): ?float
    {
        $lighter = self::luminance($fore);
        $darker = self::luminance($back);

        if ($lighter === null || $darker === null) {
            return null;
        }

        if ($lighter < $darker) {
            [$lighter, $darker] = [$darker, $lighter];
        }

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(?string $colour): ?float
    {
        if ($colour === null || preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $colour, $match) !== 1) {
            return null;
        }

        $hex = strlen($match[1]) === 3 ? preg_replace('/(.)/', '$1$1', $match[1]) : $match[1];

        $channels = array_map(static function (string $pair): float {
            $channel = hexdec($pair) / 255;

            return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, str_split((string) $hex, 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
