<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Resolvers;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * A collection by a filter (§3.2): the conditions of the storefront's facets, asked of the database
 * rather than the engine. The result is cached, so a collection needs no index of its own.
 */
final class FilterResolver implements CollectionResolver
{
    public function __construct(private readonly Facets $facets) {}

    public function source(): string
    {
        return ProductCollection::FILTER;
    }

    public function ids(ProductCollection $collection, int $limit, string $locale): array
    {
        $query = CollectionQuery::of($collection, $limit, $locale);

        if ($collection->category_id !== null) {
            $query = $query->category($collection->category_id);
        }

        $state = $collection->state();

        if (! $state->isEmpty()) {
            $facets = $this->facets;

            /** @param Builder<Product> $products */
            $narrow = static function (Builder $products) use ($state, $facets): void {
                foreach ($state->all() as $key => $value) {
                    $facets->find($key)?->applySql($products, $value);
                }
            };

            $query = $query->narrowedBy('set', $narrow);
        }

        if ($collection->on_sale) {
            // Not a facet: one would reach the storefront and the index for the sake of this (§3.4).
            // The comparison alone already leaves out an empty old price; `whereNotNull` is there
            // for the index, which cannot answer a comparison of two columns.
            $query = $query->narrowedBy('on-sale', static function (Builder $products): void {
                $products->whereNotNull('catalog_products.old_price')
                    ->whereColumn('catalog_products.old_price', '>', 'catalog_products.price');
            });
        }

        if ($collection->sort !== null) {
            $query = $query->sort($collection->sort);
        }

        return CollectionQuery::ids($query);
    }
}
