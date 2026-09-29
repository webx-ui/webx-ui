<?php

declare(strict_types=1);

use WebxUi\Catalog\Rendering\ProductQuery;

if (! function_exists('products')) {
    /**
     * `products()` — the products a template may show, as cards (see {@see ProductQuery}).
     *
     *     products()->category('shoes')->sort('popular')->take(8);
     *     products()->only([12, 7, 30]);
     *     products()->except($product)->category($product->category_id)->take(4);
     *
     * Guarded, because the name is short enough that a site may have taken it first — and a
     * package that redeclares a function of the application is a fatal error at boot.
     */
    function products(): ProductQuery
    {
        return new ProductQuery;
    }
}
