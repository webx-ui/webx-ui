<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Models\Product;

/**
 * The price as a range: registered only while the price is switched on, so a site without prices
 * has no such facet anywhere — not in the filter, not in the panel, not in an address.
 *
 * Never open to the index: `price_100-500` is a page nobody searches for (§8.1 of the
 * architecture). A product without a price is outside every chosen range, and outside the ends
 * the engine reports.
 */
final class PriceFacet extends AbstractFacet
{
    public const KEY = 'price';

    public function key(): string
    {
        return self::KEY;
    }

    public function code(): string
    {
        return self::KEY;
    }

    public function kind(): FacetKind
    {
        return FacetKind::Range;
    }

    public function label(): string
    {
        return (string) __('webx-catalog::storefront.facet-price');
    }

    public function indexable(): bool
    {
        return false;
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, FacetValue $value): void
    {
        $column = $query->getModel()->qualifyColumn('price');

        if ($value->min !== null) {
            $query->where($column, '>=', $value->min);
        }

        if ($value->max !== null) {
            $query->where($column, '<=', $value->max);
        }
    }

    public function sqlValues(QueryBuilder $products): QueryBuilder
    {
        return $products->newQuery()
            ->from('catalog_products')
            ->select(['id as product_id', 'price as value'])
            ->whereIn('id', clone $products)
            ->whereNotNull('price');
    }
}
