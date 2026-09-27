<?php

declare(strict_types=1);

namespace WebxUi\Press\Rendering;

use WebxUi\Localization\Locales;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;

/**
 * Everything the parts of an outlet's page print, worked out once (§4.4) — so a part a site
 * publishes and rewrites is markup over plain data, and no part asks the database for itself.
 *
 *     $outlet     the model — for `extra()`, SEO and anything a site's part wants of it
 *     $card       the outlet's card (see {@see Cards}): title, summary, logo, website, count…
 *     $title      the name, from any language that has it
 *     $summary    in this language, or ''
 *     $logo       the logo resolved, or null — the name is printed instead
 *     $website    the outlet's own site, or null
 *     $articles   the cards of the articles seen here, in the outlet's own order
 */
final class OutletPage
{
    public function __construct(
        private readonly Cards $cards,
        private readonly Locales $locales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Outlet $outlet): array
    {
        $locale = $this->locales->current();
        $card = $this->cards->outlet($outlet, $locale);

        $articles = $outlet->articles
            ->filter(static fn (Article $article): bool => $article->visibleIn($locale))
            ->each(static fn (Article $article) => $article->setRelation('outlet', $outlet))
            ->values()
            ->all();

        return [
            'outlet' => $outlet,
            'card' => $card,
            'title' => $card['title'],
            'summary' => $card['summary'],
            'logo' => $card['logo'],
            'website' => $card['website'],
            'articles' => $this->cards->articles($articles, $locale),
        ];
    }
}
