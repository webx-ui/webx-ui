<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Catalog;

use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogProperties\Models\Property;

/**
 * The sets of the categories (§3.2 of the properties spec).
 *
 * A category's set in force is the properties of every ancestor from the root, then its own — read
 * in one query by the tree's bounds and kept in the cache per category. What a category stores is
 * only its own rows; inheriting is reading. A property an ancestor already has cannot be added
 * below it (a 422 naming the ancestor): otherwise «taking it out» of the descendant would mean
 * «moving it».
 *
 * Only the set of a product's **main** category counts (decision 5): the form, the facets, the card
 * and the document see those properties, and a value of any other is kept but shown nowhere
 * (decision 6). {@see whereInSet()} is that rule as SQL, for the queries that read many products.
 *
 * One generation in the cache for every category, as the core's facet settings do: a save of any
 * set, or a move of a branch, changes whom a whole subtree inherits from, and a per-category key
 * cannot see the tree.
 */
final class PropertySets
{
    private const GENERATION = 'webx.catalog-properties.sets.generation';

    public const TABLE = 'catalog_category_property';

    /** @var array<int, list<array{property: int, from: int}>> */
    private array $memo = [];

    public function __construct(
        private readonly Cache $cache,
        private readonly Properties $properties,
    ) {}

    /**
     * The ids of the properties in force, ancestors first, each in its owner's order; a property
     * in the bin is left out.
     *
     * @return list<int>
     */
    public function effective(Category|int|null $category): array
    {
        return array_map(static fn (array $row): int => $row['property'], $this->rows($category));
    }

    /**
     * The same, with the category each property comes from.
     *
     * @return list<array{property: int, from: int}>
     */
    public function rows(Category|int|null $category): array
    {
        $id = $category instanceof Category ? (int) $category->getKey() : $category;

        if ($id === null || $id <= 0) {
            return [];
        }

        if (! isset($this->memo[$id])) {
            /** @var list<array{property: int, from: int}> $rows */
            $rows = $this->cache->rememberForever('webx.catalog-properties.sets.'.$this->generation().'.'.$id, fn (): array => $this->lookup($id));
            $this->memo[$id] = $rows;
        }

        $live = $this->properties->all();

        return array_values(array_filter($this->memo[$id], static fn (array $row): bool => isset($live[$row['property']])));
    }

    /**
     * The category's own properties, in order.
     *
     * @return list<int>
     */
    public function own(Category $category): array
    {
        return DB::table(self::TABLE)
            ->where('category_id', $category->getKey())
            ->orderBy('position')
            ->pluck('property_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Write the category's own set, in the order given, and mark every product of its subtree in
     * one statement.
     *
     * @param  list<int>  $ids
     *
     * @throws ValidationException naming the ancestor that already has a property, or an unknown one
     */
    public function save(Category $category, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $known = Property::query()->whereKey($ids)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $unknown = array_diff($ids, $known);

        if ($unknown !== []) {
            throw ValidationException::withMessages(['ids' => [(string) __('webx-catalog-properties::errors.unknown-property')]]);
        }

        $parent = $category->parent_id === null ? null : (int) $category->parent_id;
        $inherited = [];

        foreach ($this->rows($parent) as $row) {
            $inherited[$row['property']] = $row['from'];
        }

        foreach ($ids as $id) {
            if (isset($inherited[$id])) {
                $from = Category::withTrashed()->find($inherited[$id]);

                throw ValidationException::withMessages(['ids' => [(string) __('webx-catalog-properties::errors.inherited', [
                    'property' => Property::withTrashed()->find($id)?->displayName() ?? '#'.$id,
                    'category' => $from instanceof Category ? $from->displayName() : '#'.$inherited[$id],
                ])]]);
            }
        }

        DB::transaction(function () use ($category, $ids): void {
            DB::table(self::TABLE)->where('category_id', $category->getKey())->delete();

            $rows = [];

            foreach ($ids as $position => $id) {
                $rows[] = ['category_id' => $category->getKey(), 'property_id' => $id, 'position' => $position];
            }

            if ($rows !== []) {
                DB::table(self::TABLE)->insert($rows);
            }
        });

        $this->forget();
        $this->touchSubtree($category);
    }

    /** Every set is stale: one was saved, or a branch moved. */
    public function forget(): void
    {
        $this->memo = [];
        $this->cache->forever(self::GENERATION, bin2hex(random_bytes(6)));
    }

    /** Forget only what this process remembers — a queue worker, between jobs. */
    public function reset(): void
    {
        $this->memo = [];
    }

    /**
     * Every product whose main category is at or under this one — one `insert … select`.
     */
    public function touchSubtree(Category $category): void
    {
        $fresh = Category::withTrashed()->find($category->getKey(), ['id', 'lft', 'rgt']);

        if (! $fresh instanceof Category) {
            return;
        }

        Container::getInstance()->make(Catalog::class)->touchQuery(
            Product::withTrashed()->whereIn('catalog_products.category_id', DB::table('catalog_categories')
                ->select('id')
                ->where('lft', '>=', $fresh->lft)
                ->where('rgt', '<=', $fresh->rgt)),
        );
    }

    /**
     * Keep the rows of `$values` (an alias of `catalog_product_property_values`) whose property is
     * in the set of their product's main category (decision 6).
     */
    public function whereInSet(QueryBuilder $query, string $values = 'catalog_product_property_values'): QueryBuilder
    {
        return $query->whereExists(static function (QueryBuilder $in) use ($values): void {
            $in->selectRaw('1')
                ->from('catalog_products as set_product')
                ->join('catalog_categories as set_main', 'set_main.id', '=', 'set_product.category_id')
                ->join('catalog_categories as set_owner', static function (JoinClause $join): void {
                    $join->on('set_owner.lft', '<=', 'set_main.lft')->on('set_owner.rgt', '>=', 'set_main.rgt');
                })
                ->join(self::TABLE.' as set_row', 'set_row.category_id', '=', 'set_owner.id')
                ->whereColumn('set_product.id', $values.'.product_id')
                ->whereColumn('set_row.property_id', $values.'.property_id');
        });
    }

    /**
     * @return list<array{property: int, from: int}>
     */
    private function lookup(int $id): array
    {
        $bounds = Category::withTrashed()->find($id, ['id', 'lft', 'rgt']);

        if (! $bounds instanceof Category) {
            return [];
        }

        $rows = DB::table(self::TABLE.' as own')
            ->join('catalog_categories as owner', 'owner.id', '=', 'own.category_id')
            ->where('owner.lft', '<=', $bounds->lft)
            ->where('owner.rgt', '>=', $bounds->rgt)
            ->orderBy('owner.lft')
            ->orderBy('own.position')
            ->get(['own.property_id', 'own.category_id']);

        $set = [];

        foreach ($rows as $row) {
            $property = (int) $row->property_id;
            // The first owner wins: a row a descendant had before an ancestor took the property is
            // not a second place for it.
            $set[$property] ??= ['property' => $property, 'from' => (int) $row->category_id];
        }

        return array_values($set);
    }

    private function generation(): string
    {
        $generation = $this->cache->get(self::GENERATION);

        return is_string($generation) ? $generation : '0';
    }
}
