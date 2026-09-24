<?php

declare(strict_types=1);

namespace WebxUi\Admin\Collections;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use WebxUi\Admin\Categories\HasCategories;
use WebxUi\Admin\Relations\HasRelations;
use WebxUi\Admin\Relations\Relations;

/**
 * Which records of a source a block shows: the value of a `wx-collection` field, read.
 *
 * What is stored is the editor's choice as they made it — `markup: null` is "the default", not
 * "off" — and what a source is handed is the choice with the default applied, so no source has
 * to know the rule: markup is on when no category was chosen (decision 8 of the FAQ spec). A
 * question shown on every page of a service must not be marked up on every one of them; the page
 * that lists them all is the one a search engine should take.
 *
 * The third filter is a relation (§3.6 of the recipes spec): only the records related to these
 * services — `related: { type: 'service', ids: [7] }` — or, with `current`, to the record whose
 * page the block is on. The second is answered at rendering time, when the page is known
 * ({@see forEntity()}); without a page (a block drawn on its sample) it filters nothing.
 */
final readonly class Selection
{
    /** The most a block may ask for; more than this is a page, not a block. */
    public const MAX_LIMIT = 100;

    /**
     * @param  list<int>  $categories  Ascending, no repeats — which is how the value is stored.
     * @param  string|null  $relatedType  The kind of record the relation filter is by; null — no such filter.
     * @param  list<int>  $relatedIds  Ascending, no repeats; empty with a type — nothing matches.
     * @param  bool  $relatedCurrent  "Related to the record whose page the block is on", not answered yet.
     */
    public function __construct(
        public array $categories = [],
        public ?int $limit = null,
        public bool $filter = false,
        public bool $markup = false,
        public ?string $relatedType = null,
        public array $relatedIds = [],
        public bool $relatedCurrent = false,
    ) {}

    /**
     * The stored value, read for a source. A value that was never written is "everything, no
     * filter" — a block put on a page and not touched shows the whole collection.
     */
    public static function of(mixed $stored, ?CollectionSource $source = null): self
    {
        $value = self::normalise($stored, $source);

        $related = $value['related'];

        // Narrowed by a relation is part of the collection just as a category is: the page that
        // lists them all is the one to mark up.
        $markup = $source !== null && $source->supportsMarkup()
            && ($value['markup'] ?? ($value['categories'] === [] && $related === null));

        return new self(
            $value['categories'],
            $value['limit'],
            $value['filter'],
            $markup,
            $related['type'] ?? null,
            $related['ids'] ?? [],
            $related['current'] ?? false,
        );
    }

    /**
     * Whatever arrived, as what is kept: ids as integers, ascending and once each; a limit in
     * range or none; flags as booleans; `markup` left null unless the editor chose. What the
     * source cannot do is dropped — categories of a source without them, markup of a source
     * that prints none, a relation to a kind of record it is not related to — so the row never
     * holds a choice nothing will act on.
     *
     * `related` is null unless it narrows something — a kind with no ids is not narrowed, and "not
     * narrowed" has one spelling. `current` is written only when it is on, which is the shape the
     * panel's field keeps (`{ type, ids }`).
     *
     * @return array{categories: list<int>, limit: int|null, filter: bool, markup: bool|null, related: array{type: string, ids: list<int>, current?: true}|null}
     */
    public static function normalise(mixed $value, ?CollectionSource $source = null): array
    {
        $value = is_array($value) ? $value : [];

        $categories = self::ids($value['categories'] ?? null);

        $limit = $value['limit'] ?? null;
        $limit = is_int($limit) || (is_string($limit) && ctype_digit($limit)) ? (int) $limit : null;
        $limit = $limit === null || $limit < 1 ? null : min($limit, self::MAX_LIMIT);

        $markup = $value['markup'] ?? null;
        $markup = is_bool($markup) ? $markup : null;

        if ($source !== null && $source->categories() === null) {
            $categories = [];
        }

        if ($source !== null && ! $source->supportsMarkup()) {
            $markup = null;
        }

        return [
            'categories' => $categories,
            'limit' => $limit,
            'filter' => ($value['filter'] ?? false) === true,
            'markup' => $markup,
            'related' => self::relatedValue($value['related'] ?? null, $source),
        ];
    }

    /** The one category chosen, when exactly one was: its own order is the one to show. */
    public function category(): ?int
    {
        return count($this->categories) === 1 ? $this->categories[0] : null;
    }

    /**
     * The choice with "related to this page" answered. The page is a record of the kind the
     * filter is by — its id; a record of another kind, or one nobody registered — nothing
     * matches; no page at all (a block drawn on its sample) — no filter, so the author sees the
     * block full.
     *
     * @param  string|null  $type  The page's record as a relation target key; null when it is none.
     */
    public function forEntity(bool $page, ?string $type = null, ?int $id = null): self
    {
        if (! $this->relatedCurrent) {
            return $this;
        }

        return new self(
            $this->categories,
            $this->limit,
            $this->filter,
            $this->markup,
            $page ? $this->relatedType : null,
            $page && $type === $this->relatedType && $id !== null ? [$id] : [],
            false,
        );
    }

    /**
     * The relation filter as a source applies it, or null for none. "Related to this page" that
     * was never answered filters nothing.
     *
     * @return array{type: string, ids: list<int>}|null
     */
    public function related(): ?array
    {
        if ($this->relatedType === null || $this->relatedCurrent) {
            return null;
        }

        return ['type' => $this->relatedType, 'ids' => $this->relatedIds];
    }

    /**
     * The choice laid over a query of records filed under categories — the part every source
     * built on {@see HasCategories} would otherwise write again.
     *
     * One category — its order (`item_position`); none or several — the order of the whole list,
     * each record once. The relation filter, on a model with {@see HasRelations}: related in any
     * role to one of the chosen records. What is visible is left to the caller: that is the
     * source's rule.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query): Builder
    {
        $model = $query->getModel();

        if (! method_exists($model, 'categoryLinks')) {
            throw new InvalidArgumentException($model::class.' is not filed under categories.');
        }

        if (count($this->categories) > 1) {
            $relation = $model->categoryLinks();

            $query->whereIn(
                $model->qualifyColumn($model->getKeyName()),
                $relation->newPivotStatement()
                    ->select($relation->getForeignPivotKeyName())
                    ->whereIn($relation->getRelatedPivotKeyName(), $this->categories),
            );
        }

        $related = $this->related();

        if ($related !== null && method_exists($model, 'relationKey')) {
            $query->whereIn(
                $model->qualifyColumn($model->getKeyName()),
                Relations::rows($model)
                    ->select('owner_id')
                    ->where('owner_type', $model->relationKey())
                    ->where('target_type', $related['type'])
                    ->whereIn('target_id', $related['ids']),
            );
        }

        // Through `scopes()` rather than the magic call: PHPStan finds no `orderedIn()` on a
        // builder of a model it only knows as `Model`.
        $query->scopes(['orderedIn' => [$this->category()]]);

        if ($this->limit !== null) {
            $query->limit($this->limit);
        }

        return $query;
    }

    /**
     * @return array{type: string, ids: list<int>, current?: true}|null
     */
    private static function relatedValue(mixed $value, ?CollectionSource $source): ?array
    {
        if (! is_array($value) || ! is_string($value['type'] ?? null) || $value['type'] === '') {
            return null;
        }

        $type = $value['type'];

        if ($source !== null && ! in_array($type, $source->relations(), true)) {
            return null;
        }

        if (($value['current'] ?? false) === true) {
            return ['type' => $type, 'ids' => [], 'current' => true];
        }

        $ids = self::ids($value['ids'] ?? null);

        return $ids === [] ? null : ['type' => $type, 'ids' => $ids];
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        $ids = [];

        foreach (is_array($value) ? $value : [] as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ids[(int) $id] = true;
            }
        }

        $ids = array_keys($ids);
        sort($ids);

        return $ids;
    }
}
