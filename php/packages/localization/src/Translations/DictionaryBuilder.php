<?php

declare(strict_types=1);

namespace WebxUi\Localization\Translations;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Str;
use WebxUi\Localization\LocaleCatalogue;
use WebxUi\Localization\Locales;

/**
 * The panel's interface strings, collected for the browser.
 *
 * The admin panel is a single-page application, so its words have to reach it as data. They
 * come from the same `lang` files the server reads — a module translates its strings once,
 * in its Composer package, and both halves of it speak the same language. The alternative,
 * a second dictionary shipped inside the npm package, is how a project ends up with two
 * stores that drift apart and only one of them translated.
 *
 * Overrides published to `lang/vendor/<namespace>/<locale>` are picked up on the way through,
 * because Laravel's own loader merges them; that is how a site adds a language the module
 * never shipped.
 */
final class DictionaryBuilder
{
    public function __construct(
        private readonly Application $app,
        private readonly Loader $loader,
        private readonly Config $config,
        private readonly Cache $cache,
        private readonly Locales $locales,
    ) {}

    /**
     * @return array{locale: string, fallback: string, namespaces: array<string, array<string, mixed>>}
     */
    public function build(string $locale): array
    {
        $locale = LocaleCatalogue::normalise($locale);

        if (! $this->caching()) {
            return $this->collect($locale);
        }

        /** @var array{locale: string, fallback: string, namespaces: array<string, array<string, mixed>>} $dictionary */
        $dictionary = $this->cache->remember(
            $this->cacheKey($locale),
            (int) $this->config->get('webx-localization.cache.ttl', 86400),
            fn (): array => $this->collect($locale),
        );

        return $dictionary;
    }

    public function forget(?string $locale = null): void
    {
        if ($locale !== null) {
            $this->cache->forget($this->cacheKey(LocaleCatalogue::normalise($locale)));

            return;
        }

        foreach ($this->locales->panel() as $panelLocale) {
            $this->cache->forget($this->cacheKey($panelLocale['code']));
        }
    }

    /**
     * @return array{locale: string, fallback: string, namespaces: array<string, array<string, mixed>>}
     */
    private function collect(string $locale): array
    {
        $fallback = $this->locales->fallback();
        $namespaces = [];

        foreach ($this->panelNamespaces() as $namespace => $path) {
            $groups = $this->groups($namespace, $path, [$locale, $fallback]);

            if ($groups === []) {
                continue;
            }

            $lines = [];

            foreach ($groups as $group) {
                // The fallback underneath rather than beside: a group half-translated should
                // show the translated lines and English for the rest, not an empty screen.
                $merged = array_replace_recursive(
                    $this->loader->load($fallback, $group, $namespace),
                    $locale === $fallback ? [] : $this->loader->load($locale, $group, $namespace),
                );

                if ($merged !== []) {
                    $lines[$group] = $merged;
                }
            }

            if ($lines !== []) {
                $namespaces[$namespace] = $lines;
            }
        }

        return [
            'locale' => $locale,
            'fallback' => $fallback,
            'namespaces' => $namespaces,
        ];
    }

    /**
     * Translation namespaces belonging to the panel, by the prefix they register under.
     *
     * @return array<string, string>
     */
    private function panelNamespaces(): array
    {
        /** @var list<string> $prefixes */
        $prefixes = (array) $this->config->get('webx-localization.namespaces', ['webx-']);

        return array_filter(
            $this->loader->namespaces(),
            static fn (string $namespace): bool => Str::startsWith($namespace, $prefixes),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Which groups a namespace has, looked for in the package and in whatever the application
     * published over it.
     *
     * @param  list<string>  $locales
     * @return list<string>
     */
    private function groups(string $namespace, string $path, array $locales): array
    {
        $directories = [];

        foreach (array_unique($locales) as $locale) {
            $directories[] = $path.DIRECTORY_SEPARATOR.$locale;
            $directories[] = $this->app->langPath('vendor'.DIRECTORY_SEPARATOR.$namespace.DIRECTORY_SEPARATOR.$locale);
        }

        $groups = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach ((array) glob($directory.DIRECTORY_SEPARATOR.'*.php') as $file) {
                if (is_string($file)) {
                    $groups[] = basename($file, '.php');
                }
            }
        }

        return array_values(array_unique($groups));
    }

    private function caching(): bool
    {
        return (bool) $this->config->get('webx-localization.cache.enabled', true);
    }

    private function cacheKey(string $locale): string
    {
        return (string) $this->config->get('webx-localization.cache.prefix', 'webx.localization')
            .'.dictionary.'.$locale;
    }
}
