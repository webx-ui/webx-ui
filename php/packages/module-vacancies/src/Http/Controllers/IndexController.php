<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Localization\Locales;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Vacancies\Models\VacancyCategory;
use WebxUi\Vacancies\Rendering\VacancyQuery;
use WebxUi\Vacancies\Rendering\Views;

/**
 * The index of the vacancies: `{prefix}`, the open ones in groups by category, and above them a
 * filter of links, `?category=<slug>` (§4.5). With the filter on there is one group; a slug nobody
 * has is an empty list, not a 404 — an old link from a job board should still land on the index.
 * No pages: the order is by hand and a site has dozens of vacancies, not thousands.
 *
 * A route rather than an entity: there is nothing to edit here. `Reserved` asks the router, so
 * the address is closed to pages while the index is on — and open to one when it is switched off.
 * The canonical is the index itself: `module-seo` keeps only `page` of a query.
 */
class IndexController
{
    /** The query parameter of the filter. */
    public const FILTER = 'category';

    public function __construct(
        private readonly Views $views,
        private readonly Seo $seo,
        private readonly Locales $locales,
    ) {}

    public function __invoke(Request $request): Response
    {
        $locale = $this->locales->current();
        $asked = $request->query(self::FILTER);
        $asked = is_string($asked) ? trim($asked) : '';

        $everything = (new VacancyQuery)->locale($locale)->groups();
        $groups = $everything;

        if ($asked !== '') {
            $category = VacancyCategory::findBySlug($asked, $locale);

            // A hidden category is no filter a reader could have been shown: nothing matches.
            $groups = $category !== null && $category->is_visible
                ? (new VacancyQuery)->locale($locale)->in((int) $category->getKey())->groups()
                : [];
        }

        $this->pushItemList($groups);

        return response($this->views->make('index', [
            'groups' => $groups,
            'filter' => $this->filter($request, $everything, $asked),
            'current' => $asked,
        ])->render());
    }

    /**
     * The links of the filter: "All", then every category that has a group on the unfiltered
     * index — a link that leads to an empty list is not one to offer. None at all when there is
     * nothing to choose between.
     *
     * @param  list<array{id: int|null, slug: string|null, title: string, vacancies: list<array<string, mixed>>}>  $groups
     * @return list<array{slug: string|null, title: string, url: string, current: bool}>
     */
    private function filter(Request $request, array $groups, string $asked): array
    {
        $links = [];

        foreach ($groups as $group) {
            if ($group['slug'] === null) {
                continue;
            }

            $links[] = [
                'slug' => $group['slug'],
                'title' => $group['title'],
                'url' => $request->url().'?'.http_build_query([self::FILTER => $group['slug']]),
                'current' => $group['slug'] === $asked,
            ];
        }

        if ($links === []) {
            return [];
        }

        return [[
            'slug' => null,
            'title' => (string) __('webx-vacancies::site.all'),
            'url' => $request->url(),
            'current' => $asked === '',
        ], ...$links];
    }

    /**
     * An `ItemList` of the vacancies on the page, each once — about the page rather than about an
     * entity, so the page pushes it.
     *
     * @param  list<array{id: int|null, slug: string|null, title: string, vacancies: list<array<string, mixed>>}>  $groups
     */
    private function pushItemList(array $groups): void
    {
        $urls = [];

        foreach ($groups as $group) {
            foreach ($group['vacancies'] as $card) {
                if (is_string($card['url'] ?? null) && $card['url'] !== '') {
                    $urls[$card['url']] = true;
                }
            }
        }

        if ($urls === []) {
            return;
        }

        $list = array_keys($urls);

        $this->seo->push([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => array_map(
                static fn (string $url, int $i): array => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $url],
                $list,
                array_keys($list),
            ),
        ]);
    }
}
