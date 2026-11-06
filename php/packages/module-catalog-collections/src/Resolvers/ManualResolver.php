<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Resolvers;

use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * The hand-picked list, in its order (§3.1). All of it goes through the rules, so a product taken
 * off sale gives its place to the next one on the list.
 */
final class ManualResolver implements CollectionResolver
{
    public function source(): string
    {
        return ProductCollection::MANUAL;
    }

    public function ids(ProductCollection $collection, int $limit, string $locale): array
    {
        $picked = $collection->products()->pluck('catalog_products.id')->map(intval(...))->all();

        if ($picked === []) {
            return [];
        }

        return CollectionQuery::ids(CollectionQuery::of($collection, $limit, $locale)->only($picked));
    }
}
