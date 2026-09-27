<?php

declare(strict_types=1);

namespace WebxUi\Press\Seo;

use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;

/**
 * schema.org for an outlet's page (decision 14): an `ItemList` of the articles seen on it, each an
 * `Article` published by the outlet as an `Organization`.
 *
 * Not for a rich result — there is none for this — but to tie the site to the outlets that wrote
 * about it, for a search engine and for an agent reading the page. `datePublished` only for a date
 * known to the day: a month or a year is not a date, and schema.org has no way to say "some time
 * in August".
 */
final class OutletMarkup
{
    /**
     * @return array<string, mixed>|null
     */
    public function of(Outlet $outlet, string $locale): ?array
    {
        $publisher = $this->publisher($outlet, $locale);
        $items = [];

        foreach ($outlet->articles as $article) {
            if (! $article->visibleIn($locale)) {
                continue;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'item' => $this->article($article, $publisher, $locale),
            ];
        }

        if ($items === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $outlet->displayTitle($locale),
            'itemListElement' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $publisher
     * @return array<string, mixed>
     */
    private function article(Article $article, array $publisher, string $locale): array
    {
        $markup = [
            '@type' => 'Article',
            'headline' => $article->text('title', $locale),
        ];

        $target = $article->target($locale);

        if ($target !== null) {
            $markup['url'] = $target;
        }

        if ($article->published_on !== null && $article->date_precision === Article::DAY) {
            $markup['datePublished'] = $article->published_on->toDateString();
        }

        $excerpt = $article->text('excerpt', $locale);

        if ($excerpt !== '') {
            $markup['description'] = $excerpt;
        }

        $markup['publisher'] = $publisher;

        return $markup;
    }

    /**
     * @return array<string, mixed>
     */
    private function publisher(Outlet $outlet, string $locale): array
    {
        $organisation = ['@type' => 'Organization', 'name' => $outlet->displayTitle($locale)];

        if ($outlet->website() !== null) {
            $organisation['url'] = $outlet->website();
        }

        $logo = $outlet->logoIn($locale);

        if ($logo !== null) {
            $organisation['logo'] = $logo['url'];
        }

        return $organisation;
    }
}
