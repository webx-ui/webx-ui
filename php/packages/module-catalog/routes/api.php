<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Catalog\Http\Controllers\BulkController;
use WebxUi\Catalog\Http\Controllers\CategoryController;
use WebxUi\Catalog\Http\Controllers\DeletedController;
use WebxUi\Catalog\Http\Controllers\ExchangeController;
use WebxUi\Catalog\Http\Controllers\ExchangeProfileController;
use WebxUi\Catalog\Http\Controllers\FacetController;
use WebxUi\Catalog\Http\Controllers\ProductController;
use WebxUi\Catalog\Http\Controllers\ProductImageController;

/*
 * The panel's API of the catalogue (§11.2). Three permissions (§11.5): `catalog.view` reads,
 * `catalog.manage` writes everything but deletes and restores, `catalog.delete` deletes, restores
 * and opens «Deleted». Categories go by the same three.
 */
Route::prefix((string) config('webx-admin.api_path').'/catalog')
    // `webx.history` by name: this group is not the frame's `api_middleware`, and without it a
    // save from the panel would be journaled as `api` with nobody behind it.
    ->middleware(['web', 'webx.panel-locale', 'cms.auth', 'webx.history'])
    ->name('webx.catalog.')
    ->group(function (): void {
        Route::middleware('cms.can:catalog.view,catalog.manage,catalog.delete')->group(function (): void {
            Route::get('products', [ProductController::class, 'index'])->name('products.index');
            Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('categories/{category}', [CategoryController::class, 'show'])->whereNumber('category')->name('categories.show');
            Route::get('facets', FacetController::class)->name('facets');
        });

        Route::middleware('cms.can:catalog.manage')->group(function (): void {
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::put('products/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');

            Route::post('products/{product}/images', [ProductImageController::class, 'store'])->whereNumber('product')->name('products.images.store');
            Route::put('products/{product}/images', [ProductImageController::class, 'update'])->whereNumber('product')->name('products.images.update');
            Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])
                ->whereNumber('product')
                ->whereNumber('image')
                ->name('products.images.destroy');
            Route::post('products/{product}/images/{image}/video', [ProductImageController::class, 'attachVideo'])
                ->whereNumber('product')
                ->whereNumber('image')
                ->name('products.images.video.store');
            Route::delete('products/{product}/images/{image}/video', [ProductImageController::class, 'detachVideo'])
                ->whereNumber('product')
                ->whereNumber('image')
                ->name('products.images.video.destroy');

            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
            Route::post('categories/{category}/move', [CategoryController::class, 'move'])->whereNumber('category')->name('categories.move');
        });

        // Each action names its own permission — a delete is `catalog.delete` — and the runner
        // asks it; the door only keeps out whoever can do none of them.
        Route::middleware('cms.can:catalog.manage,catalog.delete')->group(function (): void {
            Route::get('bulk', [BulkController::class, 'index'])->name('bulk.index');
            Route::post('bulk', [BulkController::class, 'store'])->name('bulk.store');
            Route::get('bulk/{run}', [BulkController::class, 'show'])->whereNumber('run')->name('bulk.show');
        });

        // The exchange (§8.2 of the exchange spec): reading and exporting behind the right to
        // open the catalogue, importing and changing profiles behind the right to write it.
        Route::prefix('exchange')->name('exchange.')->group(function (): void {
            Route::middleware('cms.can:catalog.view,catalog.manage,catalog.delete')->group(function (): void {
                Route::get('columns', [ExchangeController::class, 'columns'])->name('columns');
                Route::post('export', [ExchangeController::class, 'export'])->name('export');
                Route::get('runs', [ExchangeController::class, 'runs'])->name('runs.index');
                Route::get('runs/{run}', [ExchangeController::class, 'show'])->whereNumber('run')->name('runs.show');
                Route::get('runs/{run}/errors', [ExchangeController::class, 'errors'])->whereNumber('run')->name('runs.errors');
                Route::get('runs/{run}/file', [ExchangeController::class, 'file'])->whereNumber('run')->name('runs.file');
                Route::get('profiles', [ExchangeProfileController::class, 'index'])->name('profiles.index');
                Route::get('profiles/{profile}', [ExchangeProfileController::class, 'show'])->whereNumber('profile')->name('profiles.show');
            });

            Route::middleware('cms.can:catalog.manage')->group(function (): void {
                Route::post('inspect', [ExchangeController::class, 'inspect'])->name('inspect');
                Route::post('import', [ExchangeController::class, 'import'])->name('import');
                Route::post('profiles', [ExchangeProfileController::class, 'store'])->name('profiles.store');
                Route::put('profiles/{profile}', [ExchangeProfileController::class, 'update'])->whereNumber('profile')->name('profiles.update');
                Route::delete('profiles/{profile}', [ExchangeProfileController::class, 'destroy'])->whereNumber('profile')->name('profiles.destroy');
            });
        });

        Route::middleware('cms.can:catalog.delete')->group(function (): void {
            Route::get('deleted', DeletedController::class)->name('deleted');

            Route::delete('products/{product}', [ProductController::class, 'destroy'])->whereNumber('product')->name('products.destroy');
            Route::post('products/{product}/restore', [ProductController::class, 'restore'])->whereNumber('product')->name('products.restore');

            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->whereNumber('category')->name('categories.destroy');
            Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->whereNumber('category')->name('categories.restore');
        });
    });

// A finished export by the signed link of its run: an agent hands it on, and whoever opens it has
// no session of the panel. The signature is the permission, and it dies with the file. The name of
// the file ends the address, so that an import by this address knows the format from it.
Route::get((string) config('webx-admin.api_path').'/catalog/exchange/download/{run}/{name}', [ExchangeController::class, 'signed'])
    ->whereNumber('run')
    ->where('name', '[a-z0-9.-]+')
    ->middleware('signed')
    ->name('webx.catalog.exchange.download');
