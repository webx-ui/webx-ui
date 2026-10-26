<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Catalog;

use Illuminate\Contracts\Container\Container;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Routing\Models\Route;

/**
 * The catalogue as the configurator sees it (§22): whether it is here at all, and the products an
 * option of a schema names by their article number — the ones a visitor may open, with the first
 * pictures of the gallery, for the block «… in real life».
 *
 * The configurator depends on nothing of the catalogue: every door to it is here, and each asks
 * {@see available()} first. In the monorepo the classes are always there, installed or not, so the
 * question is put to the container and the panel, as the inbox's is.
 */
final class CatalogProducts
{
    /** How many pictures of the gallery the block shows: enough to see the thing, not a gallery. */
    public const int PICTURES = 4;

    /** The longest article number an option may name — the column of the catalogue. */
    public const int SKU = 64;

    public function __construct(private readonly Container $app) {}

    /** Whether the catalogue is installed and booted on this site. */
    public function available(): bool
    {
        return class_exists(Product::class)
            && $this->app->bound(ProductParts::class)
            && $this->app->make(ModuleRegistry::class)->has('catalog');
    }

    /**
     * The article numbers of these that some product has, in the bin included — what the check of a
     * schema asks about a new one (`product.unknown`). A product in the bin is still a product: an
     * option keeps it and the site simply shows nothing for it.
     *
     * @param  list<string>  $skus
     * @return list<string>
     */
    public function known(array $skus): array
    {
        if ($skus === [] || ! $this->available()) {
            return $skus;
        }

        return Product::withTrashed()->whereIn('sku', $skus)->pluck('sku')->map(strval(...))->all();
    }

    /**
     * The products the site shows, by article number: the name, the address and up to four pictures
     * of the gallery in its order. A product unpublished, binned or in no visible category is not
     * here — the block for it is not printed. Three queries for the whole configurator, whatever the
     * number of options: the products, their pictures, their addresses.
     *
     * @param  list<string>  $skus
     * @return array<string, array{id: int, sku: string, name: string, url: string, images: list<array{src: string, thumb: string|null, alt: string}>}>
     */
    public function live(array $skus, string $locale): array
    {
        $skus = array_values(array_unique(array_filter($skus, static fn (string $sku): bool => $sku !== '')));

        if ($skus === [] || ! $this->available()) {
            return [];
        }

        $products = Product::query()
            ->visible($locale)
            ->whereIn('sku', $skus)
            ->with([
                'images' => static fn ($images) => $images->whereNotNull('path'),
                'routes' => static fn ($routes) => $routes->where('locale', $locale)->where('kind', Route::CANONICAL),
            ])
            ->get();

        $found = [];

        foreach ($products as $product) {
            if (! $product instanceof Product || ! $product->hasUrlIn($locale)) {
                continue;
            }

            $name = $product->getTranslation('name', $locale);

            $found[(string) $product->sku] = [
                'id' => (int) $product->getKey(),
                'sku' => (string) $product->sku,
                'name' => is_string($name) && $name !== '' ? $name : (string) $product->sku,
                'url' => $product->url($locale),
                'images' => $this->pictures($product, $locale),
            ];
        }

        return $found;
    }

    /**
     * The products by article number for the panel and an agent: whether each is there and on the
     * site, by name — `configurators_get` says what an option points at.
     *
     * @param  list<string>  $skus
     * @return array<string, array{id: int, name: string, visible: bool, trashed: bool}>
     */
    public function describe(array $skus, string $locale): array
    {
        if ($skus === [] || ! $this->available()) {
            return [];
        }

        $visible = Product::query()->visible($locale)->whereIn('sku', $skus)->pluck('id')->map(intval(...))->all();
        $found = [];

        foreach (Product::withTrashed()->whereIn('sku', $skus)->get() as $product) {
            $name = $product->getTranslation('name', $locale);

            $found[(string) $product->sku] = [
                'id' => (int) $product->getKey(),
                'name' => is_string($name) && $name !== '' ? $name : (string) $product->sku,
                'visible' => in_array((int) $product->getKey(), $visible, true),
                'trashed' => $product->trashed(),
            ];
        }

        return $found;
    }

    /**
     * The same, by id — the products that open a configurator, which name it rather than the other
     * way round.
     *
     * @param  list<int>  $ids
     * @return array<int, array{sku: string|null, name: string, visible: bool, trashed: bool}>
     */
    public function byIds(array $ids, string $locale): array
    {
        if ($ids === [] || ! $this->available()) {
            return [];
        }

        $visible = Product::query()->visible($locale)->whereKey($ids)->pluck('id')->map(intval(...))->all();
        $found = [];

        foreach (Product::withTrashed()->whereKey($ids)->get() as $product) {
            $name = $product->getTranslation('name', $locale);

            $found[(int) $product->getKey()] = [
                'sku' => $product->sku,
                'name' => is_string($name) && $name !== '' ? $name : '#'.$product->getKey(),
                'visible' => in_array((int) $product->getKey(), $visible, true),
                'trashed' => $product->trashed(),
            ];
        }

        return $found;
    }

    /**
     * @return list<array{src: string, thumb: string|null, alt: string}>
     */
    private function pictures(Product $product, string $locale): array
    {
        $pictures = [];

        foreach ($product->images as $image) {
            if (! $image instanceof ProductImage) {
                continue;
            }

            $alt = $image->getTranslation('alt', $locale);

            $pictures[] = [
                'src' => $image->url(),
                'thumb' => $image->thumbUrl(480, 480, 'cover'),
                'alt' => is_string($alt) ? $alt : '',
            ];

            if (count($pictures) === self::PICTURES) {
                break;
            }
        }

        return $pictures;
    }
}
