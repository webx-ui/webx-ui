<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Closure;
use Illuminate\Contracts\Translation\Loader;

/**
 * The words of the layout — `theme::theme.read-more` (spec §15.2) — looked up through the chain.
 *
 * Laravel gives a namespace one directory, and the chain has several: theme-default carries the
 * dictionary in every language of the panel, a local theme adds and overrides only its own keys.
 * So the `theme` namespace is answered here, key by key, bottom layer first and each layer above
 * on top; the site's own `lang/vendor/theme/<locale>/<group>.php` comes last and wins, as the
 * site's `resources/views` does for views. Every other namespace goes to the loader this wraps.
 */
final class ThemeLines implements Loader
{
    public const string NAMESPACE = 'theme';

    /**
     * @param  Closure(): ThemeChain  $chain  Lazy: the loader is built before the theme is
     *                                        known to be configured, and a site that never asks
     *                                        for a `theme::` word never resolves the chain here.
     * @param  Closure(): string  $langPath
     */
    public function __construct(
        private readonly Loader $loader,
        private readonly Closure $chain,
        private readonly Closure $langPath,
    ) {}

    /** @return array<array-key, mixed> */
    public function load($locale, $group, $namespace = null)
    {
        if ($namespace !== self::NAMESPACE) {
            return $this->loader->load($locale, $group, $namespace);
        }

        $directories = [
            ...array_reverse(($this->chain)()->langPaths()),
            ($this->langPath)().'/vendor/'.self::NAMESPACE,
        ];

        $lines = [];

        foreach ($directories as $directory) {
            $file = "{$directory}/{$locale}/{$group}.php";

            if (is_file($file)) {
                $lines = array_replace_recursive($lines, (array) require $file);
            }
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->loader->addJsonPath($path);
    }

    /** @return array<string, string> */
    public function namespaces()
    {
        return $this->loader->namespaces();
    }

    /**
     * What the wrapped loader has beyond the contract — `FileLoader::addPath()`, `paths()` — for
     * whoever reaches for it through `app('translation.loader')`.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->loader->{$method}(...$arguments);
    }
}
