<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;

/**
 * `reviews()` — the reviews a template may show, as cards rather than models (§4.4).
 *
 *     reviews()->in($categories)->take($limit ?: 6)   // an editor's choice; empty is every review
 *     reviews()->in(3)                                // one category, in its own order
 *     reviews()->only([12, 7])                        // these, in this order
 *     reviews()->except($review)                      // all but these
 *     reviews()->categories()                         // the catalogue, grouped
 *
 * The same steps and the same meaning as `services()` — both are a {@see RecordQuery} — so a site
 * that learnt one knows the other. What a reader may see is not a step: published, out of the bin
 * and with a text in the language being read (decision 7). The shape of a card is {@see Cards},
 * the same one a `wx-collection` field hands over.
 *
 * Review categories have no slugs, so a category is named by its id or by itself; a string is
 * read as an id only when it is one. Any other string is a filter nothing passes rather than no
 * filter — a typo must not quietly turn "the reviews about implants" into every review there is.
 *
 * @extends RecordQuery<Review>
 */
final class ReviewQuery extends RecordQuery
{
    /**
     * Only what is filed under these categories: an id, a category, or a list of them.
     *
     * Nothing — null, an empty string or list — is no filter at all, because that is what an
     * editor's untouched field sends and "every review" is what it means.
     *
     * @param  int|string|ReviewCategory|iterable<int|string|ReviewCategory>|null  $categories
     */
    public function in(int|string|ReviewCategory|iterable|null $categories): self
    {
        return $this->withCategories($categories);
    }

    /**
     * The visible categories, in their order, each with the reviews it lists in its own order.
     * `in()` narrows the categories, `only()`, `except()` and the language apply to the reviews
     * inside, and `take()` to each category rather than the whole.
     *
     * A category with nothing left to show is left out: a heading over nothing is not a group.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        $locale = $this->resolvedLocale();
        $ids = $this->categoryIds($locale);

        if ($ids === []) {
            return [];
        }

        $only = $this->onlyIds();
        $except = $this->exceptIds();

        $query = ReviewCategory::query()
            ->visible()
            ->ordered()
            ->with([
                'reviews' => static function (Relation $reviews) use ($locale, $only, $except): void {
                    $reviews->where('reviews.published', true)->whereNotNull('reviews.text->'.$locale);

                    if ($except !== []) {
                        $reviews->whereNotIn('reviews.id', $except);
                    }

                    if ($only !== null) {
                        $reviews->whereIn('reviews.id', $only === [] ? [0] : $only);
                    }

                    $reviews->orderBy('review_category_review.item_position')
                        ->orderBy('reviews.position')
                        ->orderBy('reviews.id');
                },
                ...array_map(static fn (string $relation): string => 'reviews.'.$relation, Cards::RELATIONS),
            ]);

        if ($ids !== null) {
            $query->whereIn('review_categories.id', $ids);
        }

        /** @var EloquentCollection<int, ReviewCategory> $categories */
        $categories = $query->get();
        $groups = [];

        foreach ($categories as $category) {
            /** @var EloquentCollection<int, Review> $reviews */
            $reviews = $category->reviews;
            $reviews = $reviews->filter(static fn (Review $review): bool => $review->writtenIn($locale));

            if ($this->limit() !== null) {
                $reviews = $reviews->take($this->limit());
            }

            if ($reviews->isEmpty()) {
                continue;
            }

            $groups[] = $this->cardMaker()->category($category, $reviews->values()->all(), $locale);
        }

        return $groups;
    }

    protected function newQuery(string $locale): Builder
    {
        return Review::query()->visibleIn($locale)->with(Cards::RELATIONS);
    }

    protected function shownIn(Model $record, string $locale): bool
    {
        return $record->writtenIn($locale);
    }

    protected function cards(array $records, string $locale): array
    {
        return $this->cardMaker()->reviews($records, $locale);
    }

    private function cardMaker(): Cards
    {
        return Container::getInstance()->make(Cards::class);
    }
}
