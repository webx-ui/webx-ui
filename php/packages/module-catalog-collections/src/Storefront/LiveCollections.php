<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Storefront;

use WebxUi\Cache\CacheGroups;
use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * The collections the site prints, by key (§5.1): one entry for the whole site, read once per
 * version of the group. A page with three collections asks the database nothing about them.
 *
 * Kept as the columns rather than the models: a site's store may unserialize no classes.
 */
final class LiveCollections
{
    public const GROUP = 'catalog-collections';

    public function __construct(private readonly CacheGroups $cache) {}

    /** The enabled collection with this key, or null: off, in the bin and unknown look the same (§4.4). */
    public function find(string $key): ?ProductCollection
    {
        $row = $this->rows()[trim($key)] ?? null;

        return $row === null ? null : (new ProductCollection)->newFromBuilder($row);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rows(): array
    {
        return $this->cache->remember('catalog-collections.live', [self::GROUP], static function (): array {
            $rows = [];

            foreach (ProductCollection::query()->where('is_enabled', true)->get() as $collection) {
                $rows[$collection->key] = $collection->getAttributes();
            }

            return $rows;
        }, localized: false);
    }
}
