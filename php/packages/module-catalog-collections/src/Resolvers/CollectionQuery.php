<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Resolvers;

use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Rendering\ProductQuery;
use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * What both ways of filling a collection share (§5.2): the catalogue's own rule of who may be seen,
 * the setting `only_purchasable`, and a limit that counts what is left after them.
 */
final class CollectionQuery
{
    public static function of(ProductCollection $collection, int $limit, string $locale): ProductQuery
    {
        $query = (new ProductQuery)->locale($locale)->take($limit);

        // Without the stock satellite nothing says what can be bought. The setting stays in the
        // record and starts to work when the satellite is installed.
        return $collection->only_purchasable && ProductQuery::hasMacro('inStock') ? $query->inStock() : $query;
    }

    /**
     * @return list<int>
     */
    public static function ids(ProductQuery $query): array
    {
        return array_values($query->models()->map(static fn (Product $product): int => (int) $product->getKey())->all());
    }
}
