<?php

declare(strict_types=1);

namespace WebxUi\Cart\Doctor;

use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Cart\Contracts\CurrencySource;

/**
 * The cart's currency in `webx:doctor`: without one the cart takes no writes, and a visitor only
 * sees a refusal. Registered only where the panel's frame is installed.
 */
final class CurrencyCheck implements Check
{
    private const string SUBJECT = 'Cart';

    public function __construct(private readonly CurrencySource $currencies) {}

    public function run(): array
    {
        $currency = $this->currencies->currency();

        if ($currency === null) {
            return [Diagnosis::warn(self::SUBJECT, 'no currency: the cart refuses every write. Set WEBX_CART_CURRENCY (webx-cart.currency), or the catalogue\'s WEBX_CATALOG_CURRENCY.')];
        }

        return [Diagnosis::ok(self::SUBJECT, "currency {$currency}.")];
    }
}
