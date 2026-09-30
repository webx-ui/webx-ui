<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\CatalogStock\StockServiceProvider;

/*
 * The list of stock statuses: read by anybody who may open the catalogue — the product form
 * chooses from it — and written with `catalog.manage` (`StockStatus::categoryKind()`).
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.catalog-stock.panel.')
    ->group(function (): void {
        CategoryRoutes::register(StockStatus::class, StockServiceProvider::SOURCE);
    });
