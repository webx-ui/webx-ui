<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Catalog;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Purchase\PurchaseRule;
use WebxUi\Catalog\Purchase\Verdict;
use WebxUi\Localization\Locales;

/**
 * "Out of stock" in the chain of `Purchasability` (§2.2 of the dictionaries spec): a product in a
 * status that cannot be bought is refused with the code `stock` and the status's own name — what
 * the button says instead of «Buy».
 *
 * Registered in the ordinary order, so it stands after the core's "not on sale" (a product that
 * is not sold is not out of stock either) and before "price on request", which the core puts last.
 */
final class StockRule implements PurchaseRule
{
    public const CODE = 'stock';

    public function refuse(Collection $products): array
    {
        $locale = app(Locales::class)->current();
        $refused = [];

        foreach (Stock::of($products->modelKeys()) as $id => $status) {
            if (! $status->is_purchasable) {
                $refused[$id] = Verdict::no(self::CODE, $status->displayName($locale));
            }
        }

        return $refused;
    }
}
