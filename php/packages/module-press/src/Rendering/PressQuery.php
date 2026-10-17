<?php

declare(strict_types=1);

namespace WebxUi\Press\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;

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
 * The steps shared with `reviews()` and `events()` and their rules are {@see RecordQuery}'s. What
 * a reader may see is not a step (decision 7): an outlet published, out of the bin and with an
 * article seen in the language being read; an article not set aside, titled in that language, in
 * such an outlet. The shape of a card is {@see Cards}.
 *
 * Two kinds of record behind one helper, which is why the kind is a step of this query rather than
 * a second class: `press()->featured()->articles()` has to carry everything said before it, and a
 * step does that by being a copy. `featured()` is about outlets — the strip of logos — and narrows
 * a feed of articles to theirs. `kind()` of an outlet is "ran an article of this kind"; the outlet
 * stays in its own order. `only()` and `except()` name outlets or articles, whichever the query
 * lists.
 *
 * @extends RecordQuery<Outlet|Article>
 */
final class PressQuery extends RecordQuery
{
    private const OUTLETS = 'outlets';

    private const ARTICLES = 'articles';

    /** The outlets, in their own order — what `press()` lists by default. */
    public function outlets(): self
    {
        return $this->withStep('listing', self::OUTLETS);
    }

    /** The articles of every outlet, by date: the latest first, the ones without a date last. */
    public function articles(): self
    {
        return $this->withStep('listing', self::ARTICLES);
    }

    /** Only the outlets marked for the strip of logos (decision 13) — or the articles of theirs. */
    public function featured(bool $featured = true): self
    {
        return $this->withStep('featured', $featured);
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

        return $this->withStep('kinds', $given === [] ? null : array_values(array_unique($given)));
    }

    /**
     * The outlets come from {@see OutletLoader} — loaded once a request, however many blocks
     * of the page ask — and are narrowed in {@see models()}; this is the query of the articles.
     *
     * @return Builder<Article>
     */
    protected function newQuery(string $locale): Builder
    {
        $featured = $this->featuredOnly();

        // Without `outlet`: the loader has every outlet an article may be seen in, with its
        // registry row, and `cards()` hands them over — one list of outlets a request, not one
        // for the articles and one for the logos.
        return Article::query()
            ->visibleIn($locale)
            ->whereIn('outlet_id', static function ($outlets) use ($featured): void {
                // Published and out of the bin — the soft-delete column by hand, since this is a
                // query on the table rather than on the model.
                $outlets->select('id')->from('press_outlets')->where('published', true)->whereNull('deleted_at');

                if ($featured) {
                    $outlets->where('featured', true);
                }
            });
    }

    /**
     * The outlets, out of the rows loaded once a request: `only`, `except`, `featured` and the
     * kinds decided here, in the order of the list, the limit last — the same steps in the
     * same order as the query of the articles takes them. The articles are the query.
     *
     * @return EloquentCollection<int, Outlet|Article>
     */
    public function models(): EloquentCollection
    {
        if ($this->listsArticles()) {
            return parent::models();
        }

        $locale = $this->resolvedLocale();
        $only = $this->onlyIds();
        $except = $this->exceptIds();
        $featured = $this->featuredOnly();
        /** @var list<Outlet|Article> $records */
        $records = [];

        foreach ($this->loader()->outlets($locale) as $outlet) {
            $id = (int) $outlet->getKey();

            if (($only !== null && ! in_array($id, $only, true)) || in_array($id, $except, true)) {
                continue;
            }

            if (($featured && ! $outlet->featured) || ! $this->shownIn($outlet, $locale)) {
                continue;
            }

            $records[] = $outlet;
        }

        if ($only !== null) {
            $order = array_flip($only);
            usort($records, static fn (Outlet $a, Outlet $b): int => ($order[(int) $a->getKey()] ?? PHP_INT_MAX) <=> ($order[(int) $b->getKey()] ?? PHP_INT_MAX));
        }

        if ($this->limit() !== null) {
            $records = array_slice($records, 0, $this->limit());
        }

        return new EloquentCollection($records);
    }

    protected function narrow(Builder $query, string $locale): void
    {
        if ($this->kinds() !== null) {
            $query->whereIn($query->getModel()->qualifyColumn('kind'), $this->kinds());
        }
    }

    protected function order(Builder $query, ?int $category): void
    {
        // Through `scopes()`: PHPStan finds no `byDate()` on a builder of a generic model.
        $query->scopes('byDate');
    }

    protected function shownIn(Model $record, string $locale): bool
    {
        if ($record instanceof Article) {
            return $record->visibleIn($locale);
        }

        return $record instanceof Outlet && $record->isVisible($locale) && $this->ranKind($record, $locale);
    }

    /**
     * @param  list<Outlet|Article>  $records
     * @return list<array<string, mixed>>
     */
    protected function cards(array $records, string $locale): array
    {
        $cards = Container::getInstance()->make(Cards::class);

        if ($this->listsArticles()) {
            $articles = array_values(array_filter($records, static fn (Model $record): bool => $record instanceof Article));
            $outlets = $this->loader()->outletsById($locale);

            // An article seen in the language is of an outlet the loader has; one it has not —
            // a row changed between the two queries — reads its own.
            foreach ($articles as $article) {
                if (isset($outlets[$article->outlet_id])) {
                    $article->setRelation('outlet', $outlets[$article->outlet_id]);
                }
            }

            return $cards->articles($articles, $locale);
        }

        return $cards->outlets(array_values(array_filter($records, static fn (Model $record): bool => $record instanceof Outlet)), $locale);
    }

    /** Whether an outlet ran an article of the kinds asked for, seen in this language. */
    private function ranKind(Outlet $outlet, string $locale): bool
    {
        $kinds = $this->kinds();

        if ($kinds === null) {
            return true;
        }

        return $outlet->articles->contains(
            static fn (Article $article): bool => $article->visibleIn($locale) && in_array($article->kindKey(), $kinds, true),
        );
    }

    private function loader(): OutletLoader
    {
        return Container::getInstance()->make(OutletLoader::class);
    }

    private function listsArticles(): bool
    {
        return $this->step('listing', self::OUTLETS) === self::ARTICLES;
    }

    private function featuredOnly(): bool
    {
        return $this->step('featured', false) === true;
    }

    /** @return list<string>|null Null — no filter. */
    private function kinds(): ?array
    {
        /** @var list<string>|null */
        return $this->step('kinds');
    }
}
