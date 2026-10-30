<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Orders;

use Illuminate\Container\Container;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Catalog\Cart\CartButton;

/**
 * Whether the product page prints the orders' «Buy in one click» (§29 of the orders' spec) beside
 * the cart's button: the orders are a module of this site and the cart sells the product.
 *
 * Only whether the tag may be printed — whether the button shows is the component's own answer
 * (the checkout and `orders.checkout.quick.enabled` on), so the catalogue does not repeat it.
 */
final class QuickButton
{
    /** The orders' module id — not their class: the catalogue does not require them. */
    public const string MODULE = 'orders';

    public static function shown(): bool
    {
        $app = Container::getInstance();

        // The registry, not class_exists: in the monorepo the orders' classes are always there.
        return CartButton::shown()
            && $app->bound(ModuleRegistry::class)
            && $app->make(ModuleRegistry::class)->has(self::MODULE);
    }
}
