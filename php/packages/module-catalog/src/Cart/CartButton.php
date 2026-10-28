<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Cart;

use Illuminate\Container\Container;
use WebxUi\Cart\Purchasables;

/**
 * Whether the product page's buy button is the cart's (§7 of the cart's spec): the cart is
 * installed and sells the catalogue's products — prices on.
 *
 * Its own class, not a method of {@see ProductPurchasable}: that one implements the cart's
 * contract, and loading it on a site without the cart would fail.
 */
final class CartButton
{
    public static function shown(): bool
    {
        $app = Container::getInstance();

        // The registry is bound by the cart's provider alone: class_exists is true in the monorepo.
        return class_exists(Purchasables::class)
            && $app->bound(Purchasables::class)
            && $app->make(Purchasables::class)->has('catalog.product');
    }
}
