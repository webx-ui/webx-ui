<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Resolvers;

use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * How a collection of one `source` is filled (§2.4 of the collections spec): a new way of filling
 * one is a class here, not a change to whoever prints it.
 */
interface CollectionResolver
{
    /** The value of `source` this class answers for. */
    public function source(): string;

    /**
     * The products to show, in their order, past the rules of §5.2.
     *
     * @return list<int>
     */
    public function ids(ProductCollection $collection, int $limit, string $locale): array;
}
