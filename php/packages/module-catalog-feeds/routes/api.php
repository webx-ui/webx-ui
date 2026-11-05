<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\CatalogFeeds\CatalogFeedsServiceProvider;
use WebxUi\CatalogFeeds\Http\FeedCategoryController;
use WebxUi\CatalogFeeds\Http\FeedController;
use WebxUi\CatalogFeeds\Http\TaxonomyController;

/*
 * The feeds (§11 of the feeds spec): read by whoever may open the catalogue, written with
 * `catalog.manage`.
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.catalog-feeds.panel.')
    ->group(function (): void {
        $path = CatalogFeedsServiceProvider::SOURCE;

        Route::middleware('cms.can:catalog.view,catalog.manage')->group(static function () use ($path): void {
            Route::get($path, [FeedController::class, 'index'])->name('index');
            Route::get($path.'/{feed}', [FeedController::class, 'show'])->whereNumber('feed')->name('show');
            Route::get($path.'/{feed}/categories', [FeedCategoryController::class, 'index'])->whereNumber('feed')->name('categories');
            Route::get($path.'/taxonomies/{key}/search', [TaxonomyController::class, 'search'])->name('taxonomies.search');
        });

        Route::middleware('cms.can:catalog.manage')->group(static function () use ($path): void {
            Route::post($path, [FeedController::class, 'store'])->name('store');
            Route::post($path.'/preview', [FeedController::class, 'preview'])->name('preview');
            Route::put($path.'/{feed}', [FeedController::class, 'update'])->whereNumber('feed')->name('update');
            Route::delete($path.'/{feed}', [FeedController::class, 'destroy'])->whereNumber('feed')->name('destroy');
            Route::post($path.'/{feed}/reissue-key', [FeedController::class, 'reissueKey'])->whereNumber('feed')->name('reissue-key');
            Route::put($path.'/{feed}/categories', [FeedCategoryController::class, 'update'])->whereNumber('feed')->name('categories.update');
            Route::post($path.'/taxonomies/{key}/import', [TaxonomyController::class, 'import'])->name('taxonomies.import');
        });
    });
