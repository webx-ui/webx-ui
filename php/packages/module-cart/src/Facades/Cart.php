<?php

declare(strict_types=1);

namespace WebxUi\Cart\Facades;

use Illuminate\Support\Facades\Facade;
use WebxUi\Cart\CartHandle;

/**
 * The visitor's cart, for a site whose own `cart()` took the helper's name.
 *
 * @method static int count()
 * @method static bool isEmpty()
 * @method static bool has(string $type, int $ref)
 * @method static \WebxUi\Cart\Totals\CartState state(?string $locale = null)
 *
 * @see CartHandle
 */
final class Cart extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CartHandle::class;
    }
}
