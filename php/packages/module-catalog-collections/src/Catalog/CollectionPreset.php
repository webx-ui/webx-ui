<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Catalog;

/**
 * A ready way of filling a collection (§3.4): a button that writes ordinary fields, not a column.
 * The manager may change them afterwards, and the collection stops being this preset.
 */
final class CollectionPreset
{
    /**
     * @param  string|null  $facet  The facet whose values the manager picks; null for none.
     */
    public function __construct(
        public readonly string $key,
        public readonly ?string $sort = null,
        public readonly bool $onSale = false,
        public readonly ?string $facet = null,
    ) {}
}
