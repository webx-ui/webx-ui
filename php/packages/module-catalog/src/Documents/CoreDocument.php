<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Documents;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\Catalog\Models\Product;

/**
 * What the core puts into a product's document (§7.3): the id, the three states and visibility,
 * the categories with every ancestor, the main one, the price, the hand-set priority, the score,
 * the date, and for the search the name in each language, the article number and the barcode.
 *
 * Unpublished and deleted products are in the index too: the panel searches with the same engine
 * as the site (decision 14 of the architecture), and the storefront cuts them off with a filter.
 * Four queries per batch whatever its size — visibility, categories, scores, and the products.
 */
final class CoreDocument implements DocumentContributor
{
    public function __construct(
        private readonly Config $config,
        private readonly CategoryFacet $categories,
    ) {}

    /** @return list<IndexField> */
    public function fields(): array
    {
        $fields = [
            new IndexField('is_published', IndexField::BOOL),
            new IndexField('is_visible', IndexField::BOOL),
            new IndexField('is_deleted', IndexField::BOOL),
            new IndexField('categories', IndexField::INT, multi: true),
            new IndexField('category_id', IndexField::INT),
            new IndexField('priority', IndexField::INT),
            new IndexField('score', IndexField::FLOAT),
            new IndexField('created_at', IndexField::TIMESTAMP),
            new IndexField('name', IndexField::TEXT, localized: true),
            new IndexField('sku', IndexField::STRING),
        ];

        if ($this->priced()) {
            $fields[] = new IndexField('price', IndexField::FLOAT);
        }

        if ((bool) $this->config->get('webx-catalog.fields.barcode', true)) {
            $fields[] = new IndexField('barcode', IndexField::STRING);
        }

        return $fields;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  list<string>  $locales
     * @return array<int, array<string, mixed>>
     */
    public function contribute(Collection $products, array $locales): array
    {
        $ids = $products->modelKeys();

        if ($ids === []) {
            return [];
        }

        $visible = array_flip(Product::query()->whereKey($ids)->visible()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());

        $categories = [];

        $batch = DB::table('catalog_products')->select('id')->whereIn('id', $ids);

        foreach ($this->categories->sqlValues($batch)->get() as $row) {
            $categories[(int) $row->product_id][] = (int) $row->value;
        }

        $scores = DB::table('catalog_product_popularity')->whereIn('product_id', $ids)->pluck('score', 'product_id');
        $barcode = (bool) $this->config->get('webx-catalog.fields.barcode', true);
        $documents = [];

        foreach ($products as $product) {
            $id = (int) $product->id;
            $document = [
                'id' => $id,
                'is_published' => (bool) $product->is_published,
                'is_visible' => isset($visible[$id]),
                'is_deleted' => $product->trashed(),
                'categories' => array_values(array_unique($categories[$id] ?? [])),
                'category_id' => $product->category_id,
                'priority' => (int) $product->priority,
                'score' => (float) ($scores[$id] ?? 0),
                'created_at' => $product->created_at?->getTimestamp(),
                'sku' => $product->sku,
            ];

            foreach ($locales as $locale) {
                $name = $product->getTranslation('name', $locale, false);
                $document['name_'.$locale] = is_string($name) ? $name : '';
            }

            if ($this->priced()) {
                $document['price'] = $product->price === null ? null : (float) $product->price;
            }

            if ($barcode) {
                $document['barcode'] = $product->barcode;
            }

            $documents[$id] = $document;
        }

        return $documents;
    }

    private function priced(): bool
    {
        return (bool) $this->config->get('webx-catalog.price.enabled', true);
    }
}
