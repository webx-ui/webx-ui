<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;

/**
 * A product as a card — what `products()` gives a template and a `wx-collection` block, one shape
 * for both, so a block's markup does not change with where its products came from.
 *
 * `id`, `anchor` and `categories` are the collection's contract; the rest is the catalogue's:
 * name, address, summary, article number, price and old price (null where prices are off), the
 * unit and the main picture with a thumbnail.
 */
final class Cards
{
    /** Wide enough for a grid of four on a laptop, small enough for a phone's two. */
    private const THUMB = 480;

    public function __construct(private readonly Config $config) {}

    /**
     * @param  list<Product>  $products
     * @return list<array<string, mixed>>
     */
    public function products(array $products, string $locale): array
    {
        $priced = (bool) $this->config->get('webx-catalog.price.enabled', true);

        return array_map(function (Product $product) use ($locale, $priced): array {
            $image = $product->mainImage();

            return [
                'id' => (int) $product->id,
                'anchor' => 'product-'.$product->id,
                'categories' => array_values(array_unique(array_filter([
                    $product->category_id,
                    ...$product->categories->map(static fn (Category $category): int => (int) $category->id)->all(),
                ]))),
                'name' => $product->displayName($locale),
                'url' => $product->listedUrl($locale),
                'summary' => $product->getTranslation('summary', $locale) ?: null,
                'sku' => $product->sku,
                'price' => $priced && $product->price !== null ? (float) $product->price : null,
                'old_price' => $priced && $product->old_price !== null ? (float) $product->old_price : null,
                'currency' => $priced ? $this->config->get('webx-catalog.price.currency') : null,
                'unit' => $product->unit,
                'image' => $image === null ? null : [
                    'url' => $image->url(),
                    'thumb' => $image->thumbUrl(self::THUMB),
                    'alt' => $image->getTranslation('alt', $locale) ?: $product->displayName($locale),
                    'width' => $image->width,
                    'height' => $image->height,
                ],
            ];
        }, $products);
    }
}
