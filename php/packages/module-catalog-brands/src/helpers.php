<?php

declare(strict_types=1);

use WebxUi\CatalogBrands\Rendering\BrandQuery;

if (! function_exists('brands')) {
    /**
     * `brands()` — the brands a template may show, as cards (see {@see BrandQuery}).
     *
     *     brands()->featured()->take(8);
     *
     * Guarded, because the name is short enough that a site may have taken it first — and a
     * package that redeclares a function of the application is a fatal error at boot.
     */
    function brands(): BrandQuery
    {
        return new BrandQuery;
    }
}
