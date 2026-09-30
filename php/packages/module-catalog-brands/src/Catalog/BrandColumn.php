<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Catalog;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Panel\ProductColumn;
use WebxUi\Localization\Locales;

/**
 * The brand in the panel's list of products: its name, and whether it is on the site.
 */
final class BrandColumn implements ProductColumn
{
    public function key(): string
    {
        return 'brand';
    }

    public function label(): string
    {
        return (string) __('webx-catalog-brands::product.brand');
    }

    public function values(Collection $products): array
    {
        $locale = app(Locales::class)->current();
        $values = [];

        foreach (Brands::of($products->modelKeys()) as $id => $brand) {
            $values[$id] = [
                'id' => $brand->id,
                'name' => $brand->displayName($locale),
                'visible' => $brand->isVisible(),
            ];
        }

        return $values;
    }

    public function sort(): ?string
    {
        return null;
    }
}
