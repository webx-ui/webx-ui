<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Catalog;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Storefront\StorefrontPart;
use WebxUi\Localization\Locales;

/**
 * The brand on the storefront: a line on a card (`catalog.card.meta`) and beside the buy button of
 * a product (`catalog.product.aside`), linking to the brand's page. A hidden brand prints nothing —
 * the product keeps it, the site does not show it.
 */
final class BrandLine implements StorefrontPart
{
    public function __construct(private readonly string $point) {}

    public function point(): string
    {
        return $this->point;
    }

    public function view(): string
    {
        return 'webx-catalog-brands::line';
    }

    public function prepare(Collection $products): array
    {
        $locale = app(Locales::class)->current();
        $brands = [];

        foreach (Brands::of($products->modelKeys(), visible: true) as $id => $brand) {
            $brands[$id] = [
                'id' => $brand->id,
                'name' => $brand->displayName($locale),
                'url' => $brand->pageUrl($locale),
                'logo' => $brand->logoUrl(),
            ];
        }

        return ['brands' => $brands, 'brandPoint' => $this->point];
    }
}
