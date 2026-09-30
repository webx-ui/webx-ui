<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Facets;

use Illuminate\Container\Container;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\Catalog\PropertySets;

/**
 * The rows `(facet_key, product_id, value)` the engine counts properties by — every facet asked
 * about in one statement (§5.2 of the properties spec).
 *
 * The engine batches by source and kind, so a batch of terms may hold reference books and
 * intervals at once: each shape is one `select`, and they are joined with `union all`. The key is
 * spelled by a `case` over the property's id rather than by concatenation, which every database
 * spells differently (and MySQL reads `||` as «or»).
 *
 * Only values of a property in the set of the product's main category are rows (decision 6).
 */
final class FacetRows
{
    public const TABLE = 'catalog_product_property_values';

    /**
     * @param  list<string>  $keys
     */
    public static function of(array $keys, QueryBuilder $products): QueryBuilder
    {
        $properties = Container::getInstance()->make(Properties::class);
        $shapes = [];

        foreach ($keys as $key) {
            $property = $properties->byFacet($key);

            if ($property === null || ! $property->is_filterable) {
                continue;
            }

            $shape = match (true) {
                $property->isTree() => 'tree',
                $property->isSelect() => 'value',
                $property->usesIntervals() => 'interval',
                $property->isNumber() => 'number',
                default => 'flag',
            };

            $shapes[$shape][] = (int) $property->id;
        }

        $parts = [];

        foreach ($shapes as $shape => $ids) {
            $parts[] = self::shape($shape, $ids, $products);
        }

        if ($parts === []) {
            return $products->newQuery()
                ->from(self::TABLE.' as stored')
                ->selectRaw("'' as facet_key, stored.product_id, stored.value_id as value")
                ->whereRaw('1 = 0');
        }

        $query = array_shift($parts);

        foreach ($parts as $part) {
            $query->unionAll($part);
        }

        return $query;
    }

    /**
     * `$column` within `[low, high)`, a null end open.
     */
    public static function within(QueryBuilder $query, string $column, ?float $low, ?float $high): void
    {
        if ($low !== null) {
            $query->where($column, '>=', $low);
        }

        if ($high !== null) {
            $query->where($column, '<', $high);
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private static function shape(string $shape, array $ids, QueryBuilder $products): QueryBuilder
    {
        $rows = $products->newQuery()
            ->from(self::TABLE.' as stored')
            ->selectRaw(self::key($ids).' as facet_key')
            ->addSelect('stored.product_id')
            ->whereIn('stored.property_id', $ids)
            ->whereIn('stored.product_id', clone $products);

        Container::getInstance()->make(PropertySets::class)->whereInSet($rows, 'stored');

        match ($shape) {
            'tree' => $rows
                ->join('catalog_property_values as node', 'node.id', '=', 'stored.value_id')
                ->join('catalog_property_values as above', static function (JoinClause $join): void {
                    $join->on('above.property_id', '=', 'node.property_id')
                        ->on('above.lft', '<=', 'node.lft')
                        ->on('above.rgt', '>=', 'node.rgt');
                })
                ->distinct()
                ->addSelect('above.id as value'),
            'value' => $rows->whereNotNull('stored.value_id')->addSelect('stored.value_id as value'),
            'interval' => $rows
                ->join('catalog_property_intervals as band', static function (JoinClause $join): void {
                    $join->on('band.property_id', '=', 'stored.property_id')
                        ->whereRaw('(band.min is null or stored.number >= band.min)')
                        ->whereRaw('(band.max is null or stored.number < band.max)');
                })
                ->whereNotNull('stored.number')
                ->addSelect('band.id as value'),
            'number' => $rows->whereNotNull('stored.number')->addSelect('stored.number as value'),
            default => $rows->where('stored.flag', true)->selectRaw('1 as value'),
        };

        return $rows;
    }

    /**
     * @param  list<int>  $ids
     */
    private static function key(array $ids): string
    {
        $case = 'case stored.property_id';

        foreach ($ids as $id) {
            $case .= ' when '.$id." then 'p.".$id."'";
        }

        return $case.' end';
    }
}
