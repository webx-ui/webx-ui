<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Cart;

use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Cart\Contracts\CurrencySource;
use WebxUi\Cart\Contracts\Purchasable;
use WebxUi\Cart\Money;
use WebxUi\Cart\PurchasableLine;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Purchase\Purchasability;
use WebxUi\Localization\Locales;

/**
 * The catalogue's product as a line of the cart (§7 of the cart's spec), registered only when
 * the cart is installed and prices are on.
 *
 * A batch costs the same whatever its size: the products with their pictures and addresses in
 * one go, and the purchase rules asked of all of them at once. A deleted product is gone (null);
 * one taken off the site, out of stock or with its price on request is a line that cannot be
 * bought now (`available = false`), its name and picture still live.
 */
final class ProductPurchasable implements Purchasable
{
    /** Small: a line of the cart, not a card of the grid. */
    private const THUMB = 320;

    public function __construct(
        private readonly Purchasability $purchasability,
        private readonly Money $money,
        private readonly CurrencySource $currencies,
        private readonly Locales $locales,
        private readonly Config $config,
    ) {}

    public function resolve(array $refs, string $locale): array
    {
        if ($refs === []) {
            return [];
        }

        $products = Product::query()->withTrashed()->whereKey($refs)->with(['images', 'routes'])->get();
        $live = $products->reject(static fn (Product $product): bool => $product->trashed());
        $verdicts = $this->purchasability->forMany($live);
        // The catalogue's currency is the one its prices are in; without one, the cart's.
        $currency = $this->config->get('webx-catalog.price.currency');
        $currency = is_string($currency) && $currency !== '' ? strtoupper($currency) : ($this->currencies->currency() ?? 'XXX');
        $lines = [];

        foreach ($live as $product) {
            $price = $product->price !== null ? $this->money->toMinor($product->price, $currency) : 0;
            $verdict = $verdicts[(int) $product->id] ?? null;
            $image = $product->mainImage();

            $lines[(int) $product->id] = new PurchasableLine(
                name: $product->displayName($locale),
                sku: $product->sku,
                price: $price,
                currency: $currency,
                image: $image === null ? null : ($image->thumbUrl(self::THUMB) ?? $image->url()),
                url: $product->listedUrl($locale),
                available: ($verdict === null || $verdict->purchasable) && $price > 0,
                names: $this->names($product),
            );
        }

        return $lines;
    }

    /** @return array<string, string> the name in every language of the site that has one */
    private function names(Product $product): array
    {
        $names = [];

        foreach ($this->locales->codes() as $code) {
            $name = $product->getTranslation('name', $code, false);

            if (is_string($name) && trim($name) !== '') {
                $names[$code] = $name;
            }
        }

        return $names;
    }
}
