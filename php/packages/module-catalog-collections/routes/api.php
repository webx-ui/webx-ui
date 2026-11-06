<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\CatalogCollections\CatalogCollectionsServiceProvider;
use WebxUi\CatalogCollections\Http\CollectionController;

/*
 * The collections (§6.5 of the collections spec): read by whoever may open the catalogue, written
 * with `catalog.manage`. The preview writes nothing, so it is a question, not a write.
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.catalog-collections.panel.')
    ->group(function (): void {
        $path = CatalogCollectionsServiceProvider::SOURCE;

        Route::middleware('cms.can:catalog.view,catalog.manage')->group(static function () use ($path): void {
            Route::get($path, [CollectionController::class, 'index'])->name('index');
            // Before `{collection}`: these are words, not ids.
            Route::get($path.'/facets', [CollectionController::class, 'facets'])->name('facets');
            Route::post($path.'/preview', [CollectionController::class, 'preview'])->name('preview');
            Route::get($path.'/{collection}', [CollectionController::class, 'show'])->whereNumber('collection')->name('show');
            Route::get($path.'/{collection}/usage', [CollectionController::class, 'usage'])->whereNumber('collection')->name('usage');
        });

        Route::middleware('cms.can:catalog.manage')->group(static function () use ($path): void {
            Route::post($path, [CollectionController::class, 'store'])->name('store');
            Route::put($path.'/{collection}', [CollectionController::class, 'update'])->whereNumber('collection')->name('update');
            Route::delete($path.'/{collection}', [CollectionController::class, 'destroy'])->whereNumber('collection')->name('destroy');
            Route::post($path.'/{collection}/restore', [CollectionController::class, 'restore'])->whereNumber('collection')->name('restore');
            Route::delete($path.'/{collection}/purge', [CollectionController::class, 'purge'])->whereNumber('collection')->name('purge');
        });
    });
