<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rendering;

use Illuminate\Contracts\Translation\Translator;
use WebxUi\Routing\SiteUrl;
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

        return [new Crumb($this->home($locale), $this->site->to('/', $locale)), ...$own];
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
