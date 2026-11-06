<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Storefront;

use WebxUi\Cache\CacheGroups;
use WebxUi\Catalog\Storefront\StorefrontCache;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Resolvers\CollectionResolvers;
use WebxUi\Localization\Locales;

/**
 * What a collection holds, as ids (§5.5): cached on the collection's own limit, one entry a
 * language however many places print it. Products move the `catalog` group, the collection its
 * own, so nothing here listens to a product.
 *
 * Ids and not models or HTML: the HTML depends on the theme and the prices, and the printing side
 * asks `products()->only($ids)` anyway, which checks who may be seen once more.
 */
final class CollectionProducts
{
    public function __construct(
        private readonly CacheGroups $cache,
        private readonly StorefrontCache $storefront,
        private readonly CollectionResolvers $resolvers,
        private readonly Locales $locales,
    ) {}

    /**
     * @param  int|null  $limit  The limit of the place it is printed in: it can only lower the
     *                           collection's own (decision 11). Nothing below one is a limit.
     * @return list<int>
     */
    public function ids(ProductCollection $collection, ?int $limit = null, ?string $locale = null): array
    {
        // The ceiling again: lowered in the config, it reaches collections saved under the old one.
        $own = max(1, min($collection->limit, ProductCollection::maxLimit()));
        $ids = $this->held($collection, $own, $locale ?? $this->locales->current());

        // Cut, never added to: the entry holds no more than the collection's own limit.
        return $limit !== null && $limit >= 1 ? array_slice($ids, 0, $limit) : $ids;
    }

    /**
     * @return list<int>
     */
    private function held(ProductCollection $collection, int $limit, string $locale): array
    {
        $resolve = fn (): array => $this->resolvers->find($collection->source)?->ids($collection, $limit, $locale) ?? [];

        if (! $this->storefront->enabled()) {
            return $resolve();
        }

        return $this->cache->remember(
            'catalog-collections.ids.'.$collection->getKey().'.'.$locale.'.'.$limit,
            [StorefrontCache::GROUP, LiveCollections::GROUP],
            $resolve,
            $this->storefront->ttl(),
            localized: false,
        );
    }
}
