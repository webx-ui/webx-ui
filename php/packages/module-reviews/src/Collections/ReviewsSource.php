<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Collections;

use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Reviews\Rendering\ReviewQuery;

/**
 * The reviews a `wx-collection` field shows: `{ "props": { "source": "reviews" } }` (§4.4).
 *
 * A thin layer over {@see ReviewQuery}, so an element here is the same card `reviews()` gives a
 * template, and who is shown is the same rule. No markup (decision 3): stars in search results
 * come only from `AggregateRating`, and Google has not shown them for what an organisation says
 * about itself on its own site since 2019 — markup with nothing to gain is only a risk.
 */
final class ReviewsSource implements CollectionSource
{
    public const KEY = 'reviews';

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return (string) __('webx-reviews::module.group');
    }

    public function categories(): string
    {
        return 'reviews/categories';
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return [];
    }

    public function supportsMarkup(): bool
    {
        return false;
    }

    public function permission(): string
    {
        return 'reviews.view';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(Selection $selection, string $locale): array
    {
        return (new ReviewQuery)
            ->in($selection->categories)
            ->take($selection->limit)
            ->locale($locale)
            ->get();
    }
}
