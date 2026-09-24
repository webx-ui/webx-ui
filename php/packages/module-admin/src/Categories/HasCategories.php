<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A record filed under categories: several of them, in an order, and the first one is the main.
 *
 * Two orders live in the link table, and they answer two questions (§2.5 of the services spec).
 * `position` is the order of the categories *on this record* — the first goes in the breadcrumbs,
 * and there is no separate switch for it, because one control beats two. `item_position` is the
 * place of this record *inside one category*, for a list the editor drags with a filter on.
 *
 * The relation is the model's and keeps its name — the blog's is still `rubrics()` — so the model
 * declares it with {@see belongsToCategories()} and says which one it is:
 *
 *     public function categoryRelation(): string { return 'rubrics'; }
 *
 *     public function rubrics(): BelongsToMany
 *     {
 *         return $this->belongsToCategories(Rubric::class, 'article_rubric');
 *     }
 *
 * @mixin Model
 */
trait HasCategories
{
    /** The name of the method that returns the relation. */
    abstract public function categoryRelation(): string;

    /**
     * The order of records when no category is chosen, as column → direction. A record added to a
     * category takes its place there by this order, so a category nobody has rearranged lists its
     * records exactly as the whole list does.
     *
     * @return array<string, 'asc'|'desc'>
     */
    public function categoryItemOrder(): array
    {
        return ['position' => 'asc', $this->getKeyName() => 'asc'];
    }

    /**
     * @return BelongsToMany<Model, $this>
     */
    public function categoryLinks(): BelongsToMany
    {
        /** @var BelongsToMany<Model, $this> $relation */
        $relation = $this->{$this->categoryRelation()}();

        return $relation;
    }

    /** The first category, or none when the record is in none. */
    public function mainCategory(): ?Model
    {
        $first = $this->getRelationValue($this->categoryRelation())?->first();

        return $first instanceof Model ? $first : null;
    }

    /**
     * Put the record in exactly these categories, in this order.
     *
     * A category the record was already in keeps its place inside it: somebody dragged it there.
     * A new one takes the place the record's position in the whole list gives it (§2.5), which is
     * what keeps an untouched category in the same order as everything else.
     *
     * @param  list<int>  $ids
     */
    public function syncCategories(array $ids): void
    {
        $relation = $this->categoryLinks();
        $ids = array_values(array_unique(array_map(intval(...), $ids)));

        $this->getConnection()->transaction(function () use ($relation, $ids): void {
            $pivot = $relation->newPivotStatement();
            $foreign = $relation->getForeignPivotKeyName();
            $related = $relation->getRelatedPivotKeyName();

            /** @var array<int, int> $kept */
            $kept = $pivot->clone()
                ->where($foreign, $this->getKey())
                ->pluck('item_position', $related)
                ->mapWithKeys(static fn (mixed $at, mixed $id): array => [(int) $id => (int) $at])
                ->all();

            $payload = [];

            foreach ($ids as $position => $id) {
                $payload[$id] = [
                    'position' => $position,
                    'item_position' => $kept[$id] ?? $this->placeIn($relation, $id),
                ];
            }

            $relation->sync($payload);
        });
    }

    /**
     * Only the records in this category.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeInCategory(Builder $query, int $category): Builder
    {
        $relation = $this->categoryLinks();

        return $query->whereIn(
            $this->qualifyColumn($this->getKeyName()),
            $relation->newPivotStatement()
                ->select($relation->getForeignPivotKeyName())
                ->where($relation->getRelatedPivotKeyName(), $category),
        );
    }

    /**
     * In the order of the whole list, or in the order of one category — the one an editor would
     * drag them in with that filter on. Asking for a category lists only its records.
     *
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function scopeOrderedIn(Builder $query, ?int $category = null): Builder
    {
        if ($category !== null) {
            $relation = $this->categoryLinks();
            $table = $relation->getTable();

            $query->select($this->qualifyColumn('*'))
                ->join($table, $relation->getQualifiedForeignPivotKeyName(), '=', $this->getQualifiedKeyName())
                ->where($table.'.'.$relation->getRelatedPivotKeyName(), $category)
                ->orderBy($table.'.item_position');
        }

        foreach ($this->categoryItemOrder() as $column => $direction) {
            $query->orderBy($this->qualifyColumn($column), $direction);
        }

        return $query;
    }

    /**
     * The relation as a category relation has to be: both orders on the pivot, and read in the
     * order the editor put the categories in.
     *
     * @param  class-string<Model>  $related
     * @return BelongsToMany<Model, $this>
     */
    protected function belongsToCategories(string $related, string $table, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null): BelongsToMany
    {
        $relation = $this->belongsToMany($related, $table, $foreignPivotKey, $relatedPivotKey)
            ->withPivot(['position', 'item_position']);

        $relation->orderBy($table.'.position')->orderBy($relation->getRelated()->getQualifiedKeyName());

        return $relation;
    }

    /**
     * Where this record goes in a category it was not in: in front of the first record that
     * comes after it in the whole list, moving the rest down by one; at the end when none does.
     *
     * @param  BelongsToMany<Model, $this>  $relation
     */
    private function placeIn(BelongsToMany $relation, int $category): int
    {
        $foreign = $relation->getForeignPivotKeyName();
        $pivot = $relation->newPivotStatement()->where($relation->getRelatedPivotKeyName(), $category);

        /** @var array<int, int> $linked */
        $linked = $pivot->clone()
            ->where($foreign, '!=', $this->getKey())
            ->pluck('item_position', $foreign)
            ->mapWithKeys(static fn (mixed $at, mixed $id): array => [(int) $id => (int) $at])
            ->all();

        if ($linked === []) {
            return 0;
        }

        // Soft-deleted records included: they still hold their place in the category, and
        // come back to it.
        $order = $this->newModelQuery()->whereKey([...array_keys($linked), $this->getKey()]);

        foreach ($this->categoryItemOrder() as $column => $direction) {
            $order->orderBy($this->qualifyColumn($column), $direction);
        }

        /** @var list<int> $ids */
        $ids = $order->pluck($this->getKeyName())->map(intval(...))->all();
        $after = array_slice($ids, (int) array_search((int) $this->getKey(), $ids, true) + 1);

        if ($after === []) {
            return max($linked) + 1;
        }

        $place = min(array_map(static fn (int $id): int => $linked[$id], $after));

        $pivot->clone()
            ->where($foreign, '!=', $this->getKey())
            ->where('item_position', '>=', $place)
            ->increment('item_position');

        return $place;
    }
}
