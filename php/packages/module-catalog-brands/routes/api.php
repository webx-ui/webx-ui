<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogBrands\Models\Brand;

/*
 * The list of brands: read by anybody who may open the catalogue — the product form chooses from
 * it — and written with `catalog.manage` (`Brand::categoryKind()`).
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.catalog-brands.panel.')
    ->group(function (): void {
        CategoryRoutes::register(Brand::class, BrandsServiceProvider::SOURCE);
    });
