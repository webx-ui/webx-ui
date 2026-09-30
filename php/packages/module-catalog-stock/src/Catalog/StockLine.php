<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Catalog;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Storefront\StorefrontPart;
use WebxUi\Localization\Locales;

/**
 * The stock status on the storefront: a line under the price of a card (`catalog.card.meta`) and
 * beside the buy button of a product (`catalog.product.aside`). One class for both points, since
 * the line is the same and only where it stands differs.
 */
final class StockLine implements StorefrontPart
{
    public function __construct(private readonly string $point) {}

    public function point(): string
    {
        return $this->point;
    }

    public function view(): string
    {
        return 'webx-catalog-stock::status';
    }

    public function prepare(Collection $products): array
    {
        $locale = app(Locales::class)->current();
        $stock = [];

        foreach (Stock::of($products->modelKeys()) as $id => $status) {
            $stock[$id] = [
                'code' => $status->code,
                'name' => $status->displayName($locale),
                'color' => $status->color,
                'purchasable' => $status->is_purchasable,
            ];
        }

        return ['stock' => $stock, 'stockPoint' => $this->point];
    }
}
