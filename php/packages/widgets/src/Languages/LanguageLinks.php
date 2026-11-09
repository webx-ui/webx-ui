<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Languages;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\URL;
use WebxUi\Localization\FirstVisit;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\RouteCache;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\Untranslated;
use WebxUi\Routing\UrlNormaliser;

/**
 * The page being served in each of the site's languages (spec §13), for the switcher.
 *
 * The addresses come from where `hreflang` takes them (module-seo's `Alternates`, ROUTING §8):
 * a page the registry found is its entity's canonical row in each language; an address outside
 * the registry — a module's own route, the blog feed — is the same path under each language's
 * prefix. Where the entity has no row and its type shows it untranslated (ROUTING §8.7), the link
 * leads to the same page under that language's prefix — the default language's words in the
 * chosen language's frame. Where it has no row otherwise, or is not shown in that language, the
 * link leads to that language's home page and says so (`fallback`): a visitor who chose Polish
 * should land on a Polish page, not on a 404. Unlike `hreflang`, a page closed to the index still switches: the
 * reader is not a search engine.
 *
 * While the language of the first visit is on (ROUTING §18), every link carries the reader's
 * choice — `?webx_lang=<code>` — which keeps it in a cookie and lands on the address without it;
 * `PageInLanguage` reads the same links without it.
 *
 * Nothing on a site with one language, without `webx-ui/routing` and `webx-ui/localization`
 * (the package requires neither), or where the language is not in the path: with `header` every
 * language has the same address, and a link cannot choose one.
 */
final class LanguageLinks
{
    /** @return list<LanguageLink> In the order the site lists its languages, the current one marked. */
    public static function current(): array
    {
        if (! class_exists(Locales::class) || ! class_exists(SiteUrl::class) || ! app()->bound(Locales::class)) {
            return [];
        }

        $request = app()->bound('request') ? app('request') : null;

        return $request instanceof Request ? app(self::class)->for($request) : [];
    }

    public function __construct(
        private readonly Locales $locales,
        private readonly SiteUrl $site,
        private readonly Config $config,
        private readonly Untranslated $untranslated,
    ) {}

    /** @return list<LanguageLink> */
    public function for(Request $request): array
    {
        $links = $this->addresses($request);
        $parameter = $this->choiceParameter();

        if ($parameter === null) {
            return $links;
        }

        return array_map(static fn (LanguageLink $link): LanguageLink => new LanguageLink(
            code: $link->code,
            name: $link->name,
            url: $link->url.(str_contains($link->url, '?') ? '&' : '?').$parameter.'='.rawurlencode($link->code),
            current: $link->current,
            fallback: $link->fallback,
            choice: true,
        ), $links);
    }

    /**
     * The page in each language, without the reader's choice: where the switcher and the
     * language of the first visit both send somebody.
     *
     * @return list<LanguageLink>
     */
    public function addresses(Request $request): array
    {
        $codes = $this->locales->codes();

        if (count($codes) < 2 || (string) $this->config->get('webx-localization.strategy', 'prefix') !== 'prefix') {
            return [];
        }

        $current = $this->locales->current();
        $paths = $this->paths($request, $codes);
        $links = [];

        foreach ($codes as $code) {
            $path = $paths[$code] ?? null;

            $links[] = new LanguageLink(
                code: $code,
                name: $this->name($code),
                url: $code === $current && $path === null ? $request->url() : URL::to($path ?? $this->site->prefix($code)),
                current: $code === $current,
                fallback: $code !== $current && $path === null,
            );
        }

        return $links;
    }

    /**
     * Language → path of this page in it; a language missing has no translation of it.
     *
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function paths(Request $request, array $codes): array
    {
        $entity = Resolution::of($request)?->entity;

        if ($entity instanceof Model) {
            return $this->entityPaths($entity);
        }

        // The registry's own route answered and found nothing: a 404 has no other language.
        $route = $request->route();

        if (! $route instanceof LaravelRoute || $route->isFallback) {
            return [];
        }

        return $this->routePaths($request->path(), $codes);
    }

    /** @return array<string, string> */
    private function entityPaths(Model $entity): array
    {
        $paths = [];

        // The rows `url()` and `hreflang` read for the same page: the request's memory, the site's
        // cache, one query — a `webx-ui/routing` from before the cache asks the registry itself.
        $rows = match (true) {
            $entity->relationLoaded('routes') => $entity->getRelation('routes'),
            class_exists(RouteCache::class) => app(RouteCache::class)->canonical($entity),
            default => Route::query()->forEntity($entity)->canonical()->get(),
        };

        /** @var iterable<Route> $rows */
        foreach ($rows as $row) {
            if ($row->kind !== Route::CANONICAL) {
                continue;
            }

            if (! $this->locales->has($row->locale)) {
                continue;
            }

            if ($entity instanceof Visible && ! $entity->isVisible($row->locale)) {
                continue;
            }

            $paths[$row->locale] = UrlNormaliser::join($this->site->prefix($row->locale), $row->path);
        }

        foreach ($this->locales->codes() as $code) {
            if (! isset($paths[$code]) && ($untranslated = $this->untranslated->path($entity, $code)) !== null) {
                $paths[$code] = $untranslated;
            }
        }

        return $paths;
    }

    /**
     * The path with its language prefix taken off and every language's put back — what
     * `hreflang` does for a route the registry does not hold.
     *
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    private function routePaths(string $path, array $codes): array
    {
        $segments = explode('/', trim($path, '/'), 2);
        $lower = array_map(static fn (string $code): string => mb_strtolower($code, 'UTF-8'), $codes);
        $rest = in_array(UrlNormaliser::key($segments[0]), $lower, true) ? ($segments[1] ?? '') : trim($path, '/');
        $paths = [];

        foreach ($codes as $code) {
            $paths[$code] = UrlNormaliser::join($this->site->prefix($code), $rest);
        }

        return $paths;
    }

    /** The parameter of the choice, while the language of the first visit is on (ROUTING §18). */
    private function choiceParameter(): ?string
    {
        if (! class_exists(FirstVisit::class)) {
            return null;
        }

        // Resolved here, not injected: the first visit asks these same links where to send.
        $visit = app(FirstVisit::class);

        return $visit->enabled() ? $visit->parameter() : null;
    }

    /** What the language calls itself: "Deutsch", "Polski" — the name a reader of it looks for. */
    private function name(string $code): string
    {
        $locale = $this->locales->find($code) ?? Locale::fromCode($code);

        return $locale->native_name !== '' ? $locale->native_name : ($locale->name !== '' ? $locale->name : strtoupper($code));
    }
}
