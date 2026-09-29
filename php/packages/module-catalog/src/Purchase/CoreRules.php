<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Purchase;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Product;

/**
 * The core's two refusals (§7.5): a product that is not on the site is not sold, and a product
 * without a price, where prices are on, is sold by asking.
 *
 * Two rules in one class because they are registered at two ends of the chain; `onSale()` and
 * `priced()` hand out each half.
 */
final class CoreRules
{
    public const UNAVAILABLE = 'unavailable';

    public const PRICE_ON_REQUEST = 'price-on-request';

    public function __construct(private readonly Config $config) {}

    public function onSale(): PurchaseRule
    {
        return new class implements PurchaseRule
        {
            public function refuse(Collection $products): array
            {
                $visible = Product::query()->whereKey($products->modelKeys())->visible()->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)->all();
                $refused = [];

                foreach ($products as $product) {
                    if ($product->trashed() || ! in_array((int) $product->id, $visible, true)) {
                        $refused[(int) $product->id] = Verdict::no(CoreRules::UNAVAILABLE, (string) __('webx-catalog::storefront.unavailable'));
                    }
                }

                return $refused;
            }
        };
    }

    public function priced(): PurchaseRule
    {
        $enabled = (bool) $this->config->get('webx-catalog.price.enabled', true);

        return new class($enabled) implements PurchaseRule
        {
            public function __construct(private readonly bool $enabled) {}

            public function refuse(Collection $products): array
            {
                if (! $this->enabled) {
                    return [];
                }

                $refused = [];

                foreach ($products as $product) {
                    if ($product->price === null) {
                        $refused[(int) $product->id] = Verdict::no(CoreRules::PRICE_ON_REQUEST, (string) __('webx-catalog::storefront.price-on-request'));
                    }
                }

                return $refused;
            }
        };
    }
}
