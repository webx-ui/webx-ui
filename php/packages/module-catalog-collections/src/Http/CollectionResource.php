<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Http;

use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogCollections\Catalog\CollectionPresets;
use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * A collection as the panel reads it: every language of its words, the set by ids, the hand-picked
 * list with what the form draws for each.
 */
final class CollectionResource
{
    public function __construct(
        private readonly Facets $facets,
        private readonly CollectionPresets $presets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function list(ProductCollection $collection): array
    {
        return [
            'id' => (int) $collection->id,
            'key' => $collection->key,
            'title' => $collection->getTranslations('title'),
            'source' => $collection->source,
            'preset' => $this->presets->of($collection)?->key,
            'view' => $collection->view,
            'limit' => (int) $collection->limit,
            'products_count' => (int) ($collection->getAttribute('products_count') ?? 0),
            'is_enabled' => (bool) $collection->is_enabled,
            'attention' => $collection->attention,
            'deleted_at' => $collection->deleted_at?->toIso8601String(),
            'updated_at' => $collection->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function full(ProductCollection $collection): array
    {
        $locale = app()->getLocale();
        $products = $collection->products()->withTrashed()->get();

        return [
            ...$this->list($collection),
            'products_count' => $products->count(),
            'category_id' => $collection->category_id,
            'filters' => (object) $collection->set()->all(),
            'filter_labels' => (object) $this->labels($collection, $locale),
            'on_sale' => (bool) $collection->on_sale,
            'sort' => $collection->sort,
            'only_purchasable' => (bool) $collection->only_purchasable,
            'link' => $collection->link,
            'link_text' => $collection->getTranslations('link_text'),
            'products' => $products->map(static fn (Product $product): int => (int) $product->id)->values()->all(),
            // What the form shows for each id without asking the products again.
            'product_items' => $products->map(static fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => $product->displayName($locale),
                'sku' => $product->sku,
                'deleted' => $product->deleted_at !== null,
            ])->values()->all(),
        ];
    }

    /**
     * What the chosen values are called, for a value the facets on offer no longer count.
     *
     * @return array<string, array<string, string>>
     */
    private function labels(ProductCollection $collection, string $locale): array
    {
        $labels = [];

        foreach ($collection->set()->all() as $key => $choice) {
            $facet = $this->facets->find($key);
            $values = array_map(strval(...), $choice['values'] ?? []);

            if ($facet !== null && $facet->kind() !== FacetKind::Range && $values !== []) {
                $labels[$key] = array_map(strval(...), $facet->labels($values, $locale));
            }
        }

        return $labels;
    }
}
