<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Catalog\Http\Controllers\CategoryController;
use WebxUi\Catalog\Http\Controllers\DeletedController;
use WebxUi\Catalog\Http\Controllers\FacetController;
use WebxUi\Catalog\Http\Controllers\ProductController;
use WebxUi\Catalog\Http\Controllers\ProductImageController;

/*
 * The panel's API of the catalogue (§11.2). Three permissions (§11.5): `catalog.view` reads,
 * `catalog.manage` writes everything but deletes and restores, `catalog.delete` deletes, restores
 * and opens «Deleted». Categories go by the same three.
 */
Route::prefix((string) config('webx-admin.api_path').'/catalog')
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
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

            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
            Route::post('categories/{category}/move', [CategoryController::class, 'move'])->whereNumber('category')->name('categories.move');
        });

        Route::middleware('cms.can:catalog.delete')->group(function (): void {
            Route::get('deleted', DeletedController::class)->name('deleted');

            Route::delete('products/{product}', [ProductController::class, 'destroy'])->whereNumber('product')->name('products.destroy');
            Route::post('products/{product}/restore', [ProductController::class, 'restore'])->whereNumber('product')->name('products.restore');

            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->whereNumber('category')->name('categories.destroy');
            Route::post('categories/{category}/restore', [CategoryController::class, 'restore'])->whereNumber('category')->name('categories.restore');
        });
    });
