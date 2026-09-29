<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;

/**
 * A product as a row of the list and as the head of the editor (§11.2).
 *
 * The name is in the panel's language, with the usual fallback — what a row shows. The editor
 * gets every language from `values`. Price, old price and barcode are left out when the site has
 * them switched off, so a panel cannot show what the site does not have.
 *
 * `visible` is §5's answer: published and in at least one visible category. A list primes it for
 * the whole page with one query (`is_visible` on the model); a single product asks for itself.
 *
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $priced = (bool) config('webx-catalog.price.enabled', true);
        $category = $product->relationLoaded('category') ? $product->category : $product->category()->first();
        $image = $product->mainImage();
        $primed = $product->getAttribute('is_visible');

        $row = [
            'id' => (int) $product->getKey(),
            'name' => $product->displayName(),
            'sku' => $product->sku,
        ];

        if ((bool) config('webx-catalog.fields.barcode', true)) {
            $row['barcode'] = $product->barcode;
        }

        if ($priced) {
            $row['price'] = $product->price === null ? null : (float) $product->price;
            $row['old_price'] = $product->old_price === null ? null : (float) $product->old_price;
        }

        return [
            ...$row,
            'unit' => $product->unit,
            'priority' => $product->priority,
            'is_published' => $product->is_published,
            'state' => $product->state(),
            'visible' => $primed === null ? $product->isVisible() : (bool) $primed,
            'category' => $category instanceof Category
                ? ['id' => (int) $category->getKey(), 'name' => $category->displayName(), 'deleted' => $category->trashed()]
                : null,
            'image' => $image === null ? null : [
                'id' => $image->id,
                'url' => $image->url(),
                'thumb' => $image->thumbUrl(160, 160),
            ],
            'url' => $this->url($product),
            'created_at' => $product->created_at?->toAtomString(),
            'updated_at' => $product->updated_at?->toAtomString(),
            'deleted_at' => $product->deleted_at?->toAtomString(),
        ];
    }

    /**
     * Where it is on the site, from the registry rows a list loaded in one go; unpublished too —
     * that is the trimmed page, which is what "Open on the site" should show (§11.1). None for a
     * deleted product: it has no address, only a redirect.
     */
    private function url(Product $product): ?string
    {
        if ($product->trashed()) {
            return null;
        }

        $locale = app(Locales::class)->current();
        $routes = $product->relationLoaded('routes') ? $product->routes : $product->routes()->get();
        $row = $routes->first(static fn (Route $route): bool => $route->kind === Route::CANONICAL && $route->locale === $locale);

        return $row === null ? null : $product->urlOf($row->path, $locale);
    }
}
