<?php

declare(strict_types=1);

namespace WebxUi\Press\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use WebxUi\Admin\Collections\RecordQuery;
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

    /** @return Builder<Outlet>|Builder<Article> */
    protected function newQuery(string $locale): Builder
    {
        $canonical = static fn (Relation $routes) => $routes->where('locale', $locale)->where('kind', Route::CANONICAL);

        if (! $this->listsArticles()) {
            return Outlet::query()->visibleIn($locale)->with(['articles', 'routes' => $canonical]);
        }

        $featured = $this->featuredOnly();

        return Article::query()
            ->visibleIn($locale)
            ->whereIn('outlet_id', static function ($outlets) use ($featured): void {
                // Published and out of the bin — the soft-delete column by hand, since this is a
                // query on the table rather than on the model.
                $outlets->select('id')->from('press_outlets')->where('published', true)->whereNull('deleted_at');

                if ($featured) {
                    $outlets->where('featured', true);
                }
            })
            ->with(['outlet.routes' => $canonical]);
    }

    protected function narrow(Builder $query, string $locale): void
    {
        if ($this->listsArticles()) {
            if ($this->kinds() !== null) {
                $query->whereIn($query->getModel()->qualifyColumn('kind'), $this->kinds());
            }

            return;
        }

        if ($this->featuredOnly()) {
            $query->where($query->getModel()->qualifyColumn('featured'), true);
        }
    }

    protected function order(Builder $query, ?int $category): void
    {
        // Through `scopes()`: PHPStan finds neither scope on a builder of the union.
        $query->scopes($query->getModel() instanceof Article ? 'byDate' : 'ordered');
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
            return $cards->articles(array_values(array_filter($records, static fn (Model $record): bool => $record instanceof Article)), $locale);
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
