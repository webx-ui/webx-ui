<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Catalog;

use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Sorts\Sorts;
use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * The presets a collection is filled by (§3.4). What a preset leaves alone is the category: "new in
 * cameras" is still the preset "new".
 */
final class CollectionPresets
{
    public const NEW = 'new';

    public const ON_SALE = 'on-sale';

    public const LABEL = 'label';

    /** @var array<string, CollectionPreset> */
    private array $presets = [];

    public function __construct(private readonly Facets $facets) {}

    public function register(CollectionPreset $preset): void
    {
        $this->presets[$preset->key] = $preset;
    }

    /**
     * What can be offered: a preset of a facet the site does not have has nothing to pick from.
     *
     * @return list<CollectionPreset>
     */
    public function all(): array
    {
        return array_values(array_filter(
            $this->presets,
            fn (CollectionPreset $preset): bool => $preset->facet === null || $this->facets->find($preset->facet) !== null,
        ));
    }

    public function find(string $key): ?CollectionPreset
    {
        foreach ($this->all() as $preset) {
            if ($preset->key === $key) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * The fields a preset writes.
     *
     * @param  list<int|string>  $values  What was picked of the preset's facet.
     * @return array{source: string, sort: string|null, on_sale: bool, filters: array<string, array{values: list<int|string>}>}
     */
    public function fields(CollectionPreset $preset, array $values = []): array
    {
        return [
            'source' => ProductCollection::FILTER,
            'sort' => $preset->sort,
            'on_sale' => $preset->onSale,
            'filters' => $preset->facet === null ? [] : [$preset->facet => ['values' => $values]],
        ];
    }

    /** The preset the fields still answer to, or null for a selection of the manager's own. */
    public function of(ProductCollection $collection): ?CollectionPreset
    {
        if ($collection->source !== ProductCollection::FILTER) {
            return null;
        }

        $sort = $collection->sort === Sorts::DEFAULT ? null : $collection->sort;
        $facets = $collection->set()->keys();

        foreach ($this->all() as $preset) {
            if ($preset->sort === $sort && $preset->onSale === $collection->on_sale
                && $facets === ($preset->facet === null ? [] : [$preset->facet])) {
                return $preset;
            }
        }

        return null;
    }
}
