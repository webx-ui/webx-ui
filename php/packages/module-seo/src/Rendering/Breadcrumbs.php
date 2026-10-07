<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\URL;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Contracts\Crumb;
use WebxUi\Seo\Contracts\HasBreadcrumbs;
use WebxUi\Settings\Settings;

/**
 * The trail of a page, home first — the one list both the `BreadcrumbList` and the visible
 * crumbs are printed from (§17.1, decision 6).
 *
 * The home is added here rather than by the entity: an article knows its rubric, not what the
 * site calls its front page, and asking every module to know that is how three modules end up
 * spelling it three ways. The name is the `seo.home-crumb` setting, the word from the dictionary
 * until somebody writes one.
 */
final class Breadcrumbs
{
    public function __construct(
        private readonly SiteUrl $site,
        private readonly Translator $translator,
        private readonly RouteTypes $types,
    ) {}

    /**
     * Home, then whatever the entity says. Nothing at all when the entity says nothing: a trail
     * of one step is the home page talking about itself.
     *
     * @return list<Crumb>
     */
    public function trail(?object $subject, string $locale): array
    {
        if (! $subject instanceof HasBreadcrumbs) {
            return [];
        }

        $own = $subject->breadcrumbs($locale);

        if ($own === []) {
            return [];
        }

        return [new Crumb($this->home($locale), $this->site->to('/', $locale)), ...$this->pagesOnly($own, $locale)];
    }

    /**
     * The steps whose address shows a page — the same line the sitemap draws.
     *
     * A category whose handler only sends the reader on ({@see \WebxUi\Routing\Contracts\NotAPage})
     * still has a canonical address, and the module's trail names it like any other. Printed, it
     * is a crumb that answers 301 and a `BreadcrumbList` item a crawler is told is a page; so it
     * is dropped here, once, for every module, rather than in each trail. The last step is the
     * page being rendered and is never asked about.
     *
     * @param  list<Crumb>  $own
     * @return list<Crumb>
     */
    private function pagesOnly(array $own, string $locale): array
    {
        $types = array_values(array_map(
            static fn (RouteType $type): string => $type->type,
            array_filter($this->types->all(), static fn (RouteType $type): bool => ! $type->servesPages()),
        ));

        if ($types === [] || count($own) < 2) {
            return $own;
        }

        $host = parse_url(URL::to('/'), PHP_URL_HOST);
        $prefix = UrlNormaliser::key($this->site->prefix($locale));
        $keys = [];

        foreach (array_slice($own, 0, -1, true) as $i => $crumb) {
            if ($crumb->url === null || parse_url($crumb->url, PHP_URL_HOST) !== $host) {
                continue;
            }

            $key = UrlNormaliser::key($crumb->url);

            if ($prefix !== '' && ($key === $prefix || str_starts_with($key, $prefix.'/'))) {
                $key = ltrim(substr($key, strlen($prefix)), '/');
            }

            $keys[$i] = $key;
        }

        if ($keys === []) {
            return $own;
        }

        $redirecting = Route::query()
            ->whereIn('entity_type', $types)
            ->where('locale', $locale)
            ->whereIn('path', array_values(array_unique($keys)))
            ->pluck('path')
            ->all();

        if ($redirecting === []) {
            return $own;
        }

        return array_values(array_filter(
            $own,
            static fn (Crumb $crumb, int $i): bool => ! isset($keys[$i]) || ! in_array($keys[$i], $redirecting, true),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * The same trail as schema.org says it. A step without an address is kept, without `item`:
     * dropping it would number the rest differently from what the reader sees.
     *
     * @param  list<Crumb>  $trail
     * @return array<string, mixed>|null
     */
    public function jsonLd(array $trail): ?array
    {
        if ($trail === []) {
            return null;
        }

        $items = [];

        foreach ($trail as $i => $crumb) {
            $item = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb->title];

            if ($crumb->url !== null) {
                $item['item'] = $crumb->url;
            }

            $items[] = $item;
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    private function home(string $locale): string
    {
        if (app()->bound(Settings::class)) {
            $written = app(Settings::class)->get('seo.home-crumb', null, $locale);

            if (is_string($written) && trim($written) !== '') {
                return trim($written);
            }
        }

        return (string) $this->translator->get('webx-seo::site.home', [], $locale);
    }
}
