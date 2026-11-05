<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\CatalogFeeds\CatalogFeedsServiceProvider;
use WebxUi\CatalogFeeds\Http\FeedController;

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
        });

        Route::middleware('cms.can:catalog.manage')->group(static function () use ($path): void {
            Route::post($path, [FeedController::class, 'store'])->name('store');
            Route::put($path.'/{feed}', [FeedController::class, 'update'])->whereNumber('feed')->name('update');
            Route::delete($path.'/{feed}', [FeedController::class, 'destroy'])->whereNumber('feed')->name('destroy');
            Route::post($path.'/{feed}/reissue-key', [FeedController::class, 'reissueKey'])->whereNumber('feed')->name('reissue-key');
        });
    });
