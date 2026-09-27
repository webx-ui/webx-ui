<?php

declare(strict_types=1);

namespace WebxUi\Press\Rendering;

use ArrayIterator;
use Countable;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use IteratorAggregate;
use Traversable;
use WebxUi\Localization\Locales;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;
use WebxUi\Routing\Models\Route;

/**
 * `press()` — the outlets and the articles a template may show, as cards rather than models
 * (§4.8).
 *
 *     press()->featured()->take(12)           // the strip of logos
 *     press()->kind('interview')              // the outlets that ran an interview
 *     press()->articles()->take(6)            // the latest articles of every outlet
 *     press()->articles()->kind(['authored']) // the articles of this kind
 *     press()->only([3, 7])                   // these, in this order
 *
 * The same steps and the same meaning as `reviews()` and `events()`. Every step returns a new
 * query. What a reader may see is not a step (decision 7): an outlet published, out of the bin and
 * with an article seen in the language being read; an article not set aside, titled in that
 * language, in such an outlet. The shape of a card is {@see Cards}.
 *
 * `featured()` is about outlets — the strip of logos — and narrows a feed of articles to theirs.
 * `kind()` of an outlet is "ran an article of this kind"; the outlet stays in its own order.
 * `only()` and `except()` name outlets or articles, whichever the query lists.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class PressQuery implements Countable, IteratorAggregate
{
    private const OUTLETS = 'outlets';

    private const ARTICLES = 'articles';

    /**
     * @param  list<string>|null  $kinds  Null — no filter; an empty list — a filter nothing passes.
     * @param  list<int>|null  $only
     * @param  list<int>  $except
     */
    public function __construct(
        private readonly string $listing = self::OUTLETS,
        private readonly bool $featured = false,
        private readonly ?array $kinds = null,
        private readonly ?array $only = null,
        private readonly array $except = [],
        private readonly ?int $limit = null,
        private readonly ?string $locale = null,
    ) {}

    /** The outlets, in their own order — what `press()` lists by default. */
    public function outlets(): self
    {
        return $this->with(listing: self::OUTLETS);
    }

    /** The articles of every outlet, by date: the latest first, the ones without a date last. */
    public function articles(): self
    {
        return $this->with(listing: self::ARTICLES);
    }

    /** Only the outlets marked for the strip of logos (decision 13) — or the articles of theirs. */
    public function featured(bool $featured = true): self
    {
        return $this->with(featured: $featured);
    }

    /**
     * Articles of these kinds; outlets that ran one. Nothing — null, an empty string or list — is
     * no filter at all, because that is what an editor's untouched field sends and "every kind" is
     * what it means.
     *
     * @param  string|iterable<string>|null  $kinds
     */
    public function kind(string|iterable|null $kinds): self
    {
        $given = [];

        foreach (is_iterable($kinds) ? $kinds : [$kinds] as $kind) {
            if (is_string($kind) && trim($kind) !== '') {
                $given[] = trim($kind);
            }
        }

        return $this->with(kinds: $given === [] ? null : array_values(array_unique($given)), kindsGiven: true);
    }

    /**
     * These and no others, in the order given — the order is the point of choosing.
     *
     * @param  int|string|Outlet|Article|iterable<int|string|Outlet|Article>  $ids
     */
    public function only(int|string|Outlet|Article|iterable $ids): self
    {
        return $this->with(only: $this->ids($ids));
    }

    /** @param  int|string|Outlet|Article|iterable<int|string|Outlet|Article>|null  $ids */
    public function except(int|string|Outlet|Article|iterable|null $ids): self
    {
        return $this->with(except: [...$this->except, ...($ids === null ? [] : $this->ids($ids))]);
    }

    /** At most this many, counted after what may not be seen is left out; null or zero — all. */
    public function take(int|string|null $limit): self
    {
        $limit = is_string($limit) && ctype_digit($limit) ? (int) $limit : $limit;

        return $this->with(limit: is_int($limit) && $limit > 0 ? $limit : null, limitGiven: true);
    }

    /** The language the cards are written in; by default, the one being rendered. */
    public function locale(?string $locale): self
    {
        return $this->with(locale: $locale, localeGiven: true);
    }

    /** @return list<array<string, mixed>> */
    public function get(): array
    {
        return $this->listing === self::ARTICLES ? $this->articleCards() : $this->outletCards();
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

    /** @return list<array<string, mixed>> */
    private function outletCards(): array
    {
        $locale = $this->resolvedLocale();

        $query = Outlet::query()
            ->visibleIn($locale)
            ->with([
                'articles',
                'routes' => static fn (Relation $routes) => $routes->where('locale', $locale)->where('kind', Route::CANONICAL),
            ])
            ->ordered();

        if ($this->featured) {
            $query->where('featured', true);
        }

        $this->narrow($query, 'press_outlets.id');

        /** @var EloquentCollection<int, Outlet> $outlets */
        $outlets = $query->get();

        $outlets = $outlets->filter(fn (Outlet $outlet): bool => $outlet->isVisible($locale) && $this->ranKind($outlet, $locale));
        $outlets = $this->arranged($outlets);

        return $this->cards()->outlets($outlets, $locale);
    }

    /** @return list<array<string, mixed>> */
    private function articleCards(): array
    {
        $locale = $this->resolvedLocale();
        $featured = $this->featured;

        $query = Article::query()
            ->visibleIn($locale)
            ->whereIn('outlet_id', static function ($outlets) use ($featured): void {
                // Published and out of the bin — the soft-delete column by hand, since this is a
                // query on the table rather than on the model.
                $outlets->select('id')->from('press_outlets')->where('published', true)->whereNull('deleted_at');

                if ($featured) {
                    $outlets->where('featured', true);
                }
            })
            ->with(['outlet.routes' => static fn (Relation $routes) => $routes->where('locale', $locale)->where('kind', Route::CANONICAL)])
            ->byDate();

        if ($this->kinds !== null) {
            $query->whereIn('kind', $this->kinds);
        }

        $this->narrow($query, 'press_articles.id');

        /** @var EloquentCollection<int, Article> $articles */
        $articles = $query->get();

        $articles = $articles->filter(static fn (Article $article): bool => $article->visibleIn($locale));
        $articles = $this->arranged($articles);

        return $this->cards()->articles($articles, $locale);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function narrow(Builder $query, string $key): void
    {
        if ($this->only !== null) {
            $query->whereIn($key, $this->only === [] ? [0] : $this->only);
        }

        if ($this->except !== []) {
            $query->whereNotIn($key, $this->except);
        }
    }

    /**
     * In the order `only()` gave, then cut to the limit — after the filters that are questions of
     * words, so that `take(6)` is six a reader sees.
     *
     * @template TModel of Outlet|Article
     *
     * @param  EloquentCollection<int, TModel>  $models
     * @return list<TModel>
     */
    private function arranged(EloquentCollection $models): array
    {
        if ($this->only !== null) {
            $order = array_flip($this->only);
            $models = $models->sortBy(static fn (Outlet|Article $model): int => $order[(int) $model->getKey()] ?? PHP_INT_MAX);
        }

        if ($this->limit !== null) {
            $models = $models->take($this->limit);
        }

        return $models->values()->all();
    }

    /** Whether an outlet ran an article of the kinds asked for, seen in this language. */
    private function ranKind(Outlet $outlet, string $locale): bool
    {
        if ($this->kinds === null) {
            return true;
        }

        return $outlet->articles->contains(
            fn (Article $article): bool => $article->visibleIn($locale) && in_array($article->kindKey(), $this->kinds, true),
        );
    }

    /**
     * @param  int|string|Outlet|Article|iterable<int|string|Outlet|Article>  $given
     * @return list<int>
     */
    private function ids(int|string|Outlet|Article|iterable $given): array
    {
        $ids = [];

        foreach (is_iterable($given) ? $given : [$given] as $one) {
            if ($one instanceof Outlet || $one instanceof Article) {
                $ids[] = (int) $one->getKey();
            } elseif (is_int($one) || (is_string($one) && ctype_digit($one))) {
                $ids[] = (int) $one;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * A copy with some steps changed. `kinds`, `limit` and `locale` may be set to null on purpose,
     * which is why each has a flag saying it was given.
     *
     * @param  list<string>|null  $kinds
     * @param  list<int>|null  $only
     * @param  list<int>|null  $except
     */
    private function with(
        ?string $listing = null,
        ?bool $featured = null,
        ?array $kinds = null,
        bool $kindsGiven = false,
        ?array $only = null,
        ?array $except = null,
        ?int $limit = null,
        bool $limitGiven = false,
        ?string $locale = null,
        bool $localeGiven = false,
    ): self {
        return new self(
            $listing ?? $this->listing,
            $featured ?? $this->featured,
            $kindsGiven ? $kinds : $this->kinds,
            $only ?? $this->only,
            $except ?? $this->except,
            $limitGiven ? $limit : $this->limit,
            $localeGiven ? $locale : $this->locale,
        );
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
