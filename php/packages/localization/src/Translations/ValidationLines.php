<?php

declare(strict_types=1);

namespace WebxUi\Localization\Translations;

use Illuminate\Contracts\Translation\Loader;

/**
 * Laravel's validation lines in every language of the panel, underneath whatever the site has.
 *
 * Laravel ships them in English only, and a site does not get `lang/<locale>/validation.php` from
 * the skeleton: a site in Ukrainian answered a bad value with `validation.array`, the key itself,
 * since even the English fallback is not there when the site's fallback language is its own.
 *
 * A wrapper rather than `FileLoader::addPath()`. A path added is read after the site's `lang/`,
 * so the package's line beat the site's own — and the loader is not always a `FileLoader` to
 * add one to: `webx-ui/themes` wraps it, and with a theme installed the lines were never added
 * at all. Here the package's lines go in first and whatever the wrapped loader finds — Laravel's
 * English, the site's `lang/` — goes on top, so a site still overrides a line by publishing it.
 */
final class ValidationLines implements Loader
{
    public const string GROUP = 'validation';

    public function __construct(
        private readonly Loader $loader,
        private readonly string $path,
    ) {}

    /** @return array<array-key, mixed> */
    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->loader->load($locale, $group, $namespace);

        if ($group !== self::GROUP || ($namespace !== null && $namespace !== '*')) {
            return $lines;
        }

        $file = "{$this->path}/{$locale}/{$group}.php";

        if (! is_file($file)) {
            return $lines;
        }

        return array_replace_recursive((array) require $file, $lines);
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
