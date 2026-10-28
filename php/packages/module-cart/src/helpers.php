<?php

declare(strict_types=1);

use WebxUi\Cart\CartHandle;

if (! function_exists('cart')) {
    /**
     * `cart()` — the visitor's cart for a template (see {@see CartHandle}).
     *
     *     <span data-webx-cart-count>{{ cart()->count() }}</span>
     *     <button @class(['is-in-cart' => cart()->has('catalog.product', $product->id)])>
     *
     * Guarded: a site that has a `cart()` of its own keeps it, and reaches this one through the
     * facade `WebxUi\Cart\Facades\Cart`.
     */
    function cart(): CartHandle
    {
        return app(CartHandle::class);
    }
}
