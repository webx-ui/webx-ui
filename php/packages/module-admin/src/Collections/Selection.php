<?php

declare(strict_types=1);

namespace WebxUi\Admin\Collections;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use WebxUi\Admin\Categories\HasCategories;

/**
 * Which records of a source a block shows: the value of a `wx-collection` field, read.
 *
 * What is stored is the editor's choice as they made it — `markup: null` is "the default", not
 * "off" — and what a source is handed is the choice with the default applied, so no source has
 * to know the rule: markup is on when no category was chosen (decision 8 of the FAQ spec). A
 * question shown on every page of a service must not be marked up on every one of them; the page
 * that lists them all is the one a search engine should take.
 */
final readonly class Selection
{
    /** The most a block may ask for; more than this is a page, not a block. */
    public const MAX_LIMIT = 100;

    /**
     * @param  list<int>  $categories  Ascending, no repeats — which is how the value is stored.
     */
    public function __construct(
        public array $categories = [],
        public ?int $limit = null,
        public bool $filter = false,
        public bool $markup = false,
    ) {}

    /**
     * The stored value, read for a source. A value that was never written is "everything, no
     * filter" — a block put on a page and not touched shows the whole collection.
     */
    public static function of(mixed $stored, ?CollectionSource $source = null): self
    {
        $value = self::normalise($stored, $source);

        $markup = $source !== null && $source->supportsMarkup()
            && ($value['markup'] ?? ($value['categories'] === []));

        return new self($value['categories'], $value['limit'], $value['filter'], $markup);
    }

    /**
     * Whatever arrived, as what is kept: ids as integers, ascending and once each; a limit in
     * range or none; flags as booleans; `markup` left null unless the editor chose. What the
     * source cannot do is dropped — categories of a source without them, markup of a source
     * that prints none — so the row never holds a choice nothing will act on.
     *
     * @return array{categories: list<int>, limit: int|null, filter: bool, markup: bool|null}
     */
    public static function normalise(mixed $value, ?CollectionSource $source = null): array
    {
        $value = is_array($value) ? $value : [];

        $categories = [];

        foreach (is_array($value['categories'] ?? null) ? $value['categories'] : [] as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $categories[(int) $id] = true;
            }
        }

        $categories = array_keys($categories);
        sort($categories);

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
        ];
    }

    /** The one category chosen, when exactly one was: its own order is the one to show. */
    public function category(): ?int
    {
        return count($this->categories) === 1 ? $this->categories[0] : null;
    }

    /**
     * The choice laid over a query of records filed under categories — the part every source
     * built on {@see HasCategories} would otherwise write again.
     *
     * One category — its order (`item_position`); none or several — the order of the whole list,
     * each record once. What is visible is left to the caller: that is the source's rule.
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

        // Through `scopes()` rather than the magic call: PHPStan finds no `orderedIn()` on a
        // builder of a model it only knows as `Model`.
        $query->scopes(['orderedIn' => [$this->category()]]);

        if ($this->limit !== null) {
            $query->limit($this->limit);
        }

        return $query;
    }
}
