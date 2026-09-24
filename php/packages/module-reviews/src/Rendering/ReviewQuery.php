<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Rendering;

use ArrayIterator;
use Countable;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use IteratorAggregate;
use Traversable;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Localization\Locales;
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
 * The same steps and the same meaning as `services()`, so a site that learnt one knows the other.
 * Every step returns a new query. What a reader may see is not a step: published, out of the bin
 * and with a text in the language being read (decision 7). The shape of a card is {@see Cards},
 * the same one a `wx-collection` field hands over.
 *
 * Review categories have no slugs, so a category is named by its id or by itself; a string is
 * read as an id only when it is one. Any other string is a filter nothing passes rather than no
 * filter — a typo must not quietly turn "the reviews about implants" into every review there is.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class ReviewQuery implements Countable, IteratorAggregate
{
    /**
     * @param  list<int>|null  $categories  Null — no filter; an empty list — a filter nothing passes.
     * @param  list<int>|null  $only
     * @param  list<int>  $except
     */
    public function __construct(
        private readonly ?array $categories = null,
        private readonly ?array $only = null,
        private readonly array $except = [],
        private readonly ?int $limit = null,
        private readonly ?string $locale = null,
    ) {}

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
        $given = [];
        $named = false;

        foreach (is_iterable($categories) ? $categories : [$categories] as $category) {
            if ($category instanceof ReviewCategory) {
                $given[] = (int) $category->getKey();
            } elseif (is_int($category) || (is_string($category) && ctype_digit($category))) {
                $given[] = (int) $category;
            } elseif (is_string($category) && trim($category) !== '') {
                $named = true;
            }
        }

        $filter = $given === [] && ! $named ? null : array_values(array_unique($given));

        return new self($filter, $this->only, $this->except, $this->limit, $this->locale);
    }

    /**
     * These reviews and no others, in the order given — the order is the point of choosing.
     *
     * @param  int|string|Review|iterable<int|string|Review>  $reviews
     */
    public function only(int|string|Review|iterable $reviews): self
    {
        return new self($this->categories, $this->ids($reviews), $this->except, $this->limit, $this->locale);
    }

    /** @param  int|string|Review|iterable<int|string|Review>|null  $reviews */
    public function except(int|string|Review|iterable|null $reviews): self
    {
        $except = $reviews === null ? [] : $this->ids($reviews);

        return new self($this->categories, $this->only, [...$this->except, ...$except], $this->limit, $this->locale);
    }

    /** At most this many; null or zero — all of them. */
    public function take(int|string|null $limit): self
    {
        $limit = is_string($limit) && ctype_digit($limit) ? (int) $limit : $limit;

        return new self($this->categories, $this->only, $this->except, is_int($limit) && $limit > 0 ? $limit : null, $this->locale);
    }

    /** The language the cards are written in; by default, the one being rendered. */
    public function locale(?string $locale): self
    {
        return new self($this->categories, $this->only, $this->except, $this->limit, $locale);
    }

    /** @return list<array<string, mixed>> */
    public function get(): array
    {
        if ($this->categories === []) {
            return [];
        }

        $locale = $this->resolvedLocale();
        $query = Review::query()->visibleIn($locale)->with(Cards::RELATIONS);

        if ($this->only !== null) {
            $query->whereIn('reviews.id', $this->only === [] ? [0] : $this->only);
        }

        if ($this->except !== []) {
            $query->whereNotIn('reviews.id', $this->except);
        }

        // No limit in SQL: whether a review is written in a language is a question of its words,
        // and the limit counts what is shown (§4.4).
        $query = (new Selection($this->categories ?? []))->apply($query);

        /** @var EloquentCollection<int, Review> $reviews */
        $reviews = $query->get();

        $reviews = $reviews->filter(static fn (Review $review): bool => $review->writtenIn($locale));

        if ($this->only !== null) {
            $order = array_flip($this->only);
            $reviews = $reviews->sortBy(static fn (Review $review): int => $order[(int) $review->getKey()] ?? PHP_INT_MAX);
        }

        if ($this->limit !== null) {
            $reviews = $reviews->take($this->limit);
        }

        return $this->cards()->reviews($reviews->values()->all(), $locale);
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        return $this->take(1)->get()[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->get() === [];
    }

    public function count(): int
    {
        return count($this->get());
    }

    /** @return Traversable<int, array<string, mixed>> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->get());
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
        if ($this->categories === []) {
            return [];
        }

        $locale = $this->resolvedLocale();

        $query = ReviewCategory::query()
            ->visible()
            ->ordered()
            ->with([
                'reviews' => function (Relation $reviews) use ($locale): void {
                    $reviews->where('reviews.published', true)->whereNotNull('reviews.text->'.$locale);

                    if ($this->except !== []) {
                        $reviews->whereNotIn('reviews.id', $this->except);
                    }

                    if ($this->only !== null) {
                        $reviews->whereIn('reviews.id', $this->only === [] ? [0] : $this->only);
                    }

                    $reviews->orderBy('review_category_review.item_position')
                        ->orderBy('reviews.position')
                        ->orderBy('reviews.id');
                },
                ...array_map(static fn (string $relation): string => 'reviews.'.$relation, Cards::RELATIONS),
            ]);

        if ($this->categories !== null) {
            $query->whereIn('review_categories.id', $this->categories);
        }

        /** @var EloquentCollection<int, ReviewCategory> $categories */
        $categories = $query->get();
        $groups = [];

        foreach ($categories as $category) {
            /** @var EloquentCollection<int, Review> $reviews */
            $reviews = $category->reviews;
            $reviews = $reviews->filter(static fn (Review $review): bool => $review->writtenIn($locale));

            if ($this->limit !== null) {
                $reviews = $reviews->take($this->limit);
            }

            if ($reviews->isEmpty()) {
                continue;
            }

            $groups[] = $this->cards()->category($category, $reviews->values()->all(), $locale);
        }

        return $groups;
    }

    /**
     * @param  int|string|Review|iterable<int|string|Review>  $reviews
     * @return list<int>
     */
    private function ids(int|string|Review|iterable $reviews): array
    {
        $ids = [];

        foreach (is_iterable($reviews) ? $reviews : [$reviews] as $review) {
            if ($review instanceof Review) {
                $ids[] = (int) $review->getKey();
            } elseif (is_int($review) || (is_string($review) && ctype_digit($review))) {
                $ids[] = (int) $review;
            }
        }

        return array_values(array_unique($ids));
    }

    private function resolvedLocale(): string
    {
        return $this->locale ?? Container::getInstance()->make(Locales::class)->current();
    }

    private function cards(): Cards
    {
        return Container::getInstance()->make(Cards::class);
    }
}
