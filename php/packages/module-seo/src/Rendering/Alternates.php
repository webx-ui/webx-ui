<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\HasUrl;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;

/**
 * The same page in the site's other languages, for `hreflang` (§17.1, decision 8).
 *
 * An entity's alternates are its own canonical rows of the registry, in the languages where it
 * is visible and open to the index — the same three questions the sitemap asks, so the `<head>`
 * of a page and its line in the map name the same set. An address without an entity (the blog
 * feed, a module's own route) is the same path under every language's prefix.
 *
 * Only where the language is in the path. With `header` there is one address for every language,
 * and an address cannot be its own alternate.
 */
final class Alternates
{
    public function __construct(
        private readonly Seo $seo,
        private readonly SiteUrl $site,
        private readonly Locales $locales,
        private readonly Config $config,
    ) {}

    /** Is there anything to say at all: more than one language, and each at an address of its own. */
    public function apply(): bool
    {
        return (string) $this->config->get('webx-localization.strategy', 'prefix') === 'prefix'
            && count($this->locales->codes()) > 1;
    }

    /**
     * Language code → absolute address, `x-default` last. Empty when the page has no second
     * language, or when it is closed to the index itself — `hreflang` on a page whose canonical
     * is another is ignored by the search engines that read it and misleading to everybody else.
     *
     * @return array<string, string>
     */
    public function for(string $url, ?object $subject, string $locale, ?SeoData $data = null): array
    {
        if (! $this->apply()) {
            return [];
        }

        $url = UrlNormaliser::normalise($url);
        [$path] = explode('?', $url, 2);
        $data ??= $this->seo->for($url, $subject, $locale);

        if ($this->seo->closedBecause($data, $path) !== null) {
            return [];
        }

        $query = $this->seo->keptQuery($url);
        $paths = $subject instanceof Model && in_array(HasUrl::class, class_uses_recursive($subject), true)
            ? $this->entityPaths($subject)
            : $this->routePaths($path);

        $alternates = [];

        foreach ($paths as $code => $candidate) {
            if ($code !== $locale) {
                if ($subject instanceof Visible && ! $subject->isVisible($code)) {
                    continue;
                }

                if (! $this->seo->indexable($candidate, $subject, $code)) {
                    continue;
                }
            }

            $alternates[self::hreflang($code)] = URL::to($candidate).$query;
        }

        if (count($alternates) < 2) {
            return [];
        }

        $default = self::hreflang($this->locales->defaultCode());

        if (isset($alternates[$default])) {
            $alternates['x-default'] = $alternates[$default];
        }

        return $alternates;
    }

    /** `pt_BR` as the attribute wants it. */
    public static function hreflang(string $code): string
    {
        return str_replace('_', '-', $code);
    }

    /**
     * The entity's canonical row in every language it has one in, prefixed.
     *
     * @return array<string, string>
     */
    private function entityPaths(Model $subject): array
    {
        $rows = Route::query()->forEntity($subject)->canonical()->get();
        $paths = [];

        foreach ($rows as $row) {
            if ($this->locales->has($row->locale)) {
                $paths[$row->locale] = '/'.UrlNormaliser::join($this->site->prefix($row->locale), $row->path);
            }
        }

        return $this->ordered($paths);
    }

    /**
     * The address with its language prefix taken off and every language's put back.
     *
     * @return array<string, string>
     */
    private function routePaths(string $path): array
    {
        $segments = explode('/', ltrim($path, '/'), 2);
        $first = UrlNormaliser::key($segments[0]);
        $codes = array_map(static fn (string $code): string => mb_strtolower($code, 'UTF-8'), $this->locales->codes());
        $rest = in_array($first, $codes, true) ? ($segments[1] ?? '') : ltrim($path, '/');

        $paths = [];

        foreach ($this->locales->codes() as $code) {
            $paths[$code] = '/'.UrlNormaliser::join($this->site->prefix($code), $rest);
        }

        return $paths;
    }

    /**
     * In the order the site lists its languages, so the lines do not shuffle between two
     * requests for the same page.
     *
     * @param  array<string, string>  $paths
     * @return array<string, string>
     */
    private function ordered(array $paths): array
    {
        $ordered = [];

        foreach ($this->locales->codes() as $code) {
            if (isset($paths[$code])) {
                $ordered[$code] = $paths[$code];
            }
        }

        return $ordered;
    }
}
