<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Catalog;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Panel\ProductColumn;
use WebxUi\Localization\Locales;

/**
 * The stock status in the panel's list of products: a tag in the status's tone.
 */
final class StockColumn implements ProductColumn
{
    public function key(): string
    {
        return 'stock';
    }

    public function label(): string
    {
        return (string) __('webx-catalog-stock::product.status');
    }

    public function values(Collection $products): array
    {
        $locale = app(Locales::class)->current();
        $values = [];

        foreach (Stock::of($products->modelKeys()) as $id => $status) {
            $values[$id] = [
                'id' => $status->id,
                'name' => $status->displayName($locale),
                'code' => $status->code,
                'color' => $status->color,
                'purchasable' => $status->is_purchasable,
            ];
        }

        return $values;
    }

    public function sort(): ?string
    {
        return null;
    }
}
