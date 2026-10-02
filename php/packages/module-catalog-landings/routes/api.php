<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\CatalogLandings\CatalogLandingsServiceProvider;
use WebxUi\CatalogLandings\Http\LandingController;

/*
 * The landings (§8.4 of the landings spec): read by whoever may open the catalogue, written with
 * `catalog.manage` (decision 13).
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.catalog-landings.panel.')
    ->group(function (): void {
        $path = CatalogLandingsServiceProvider::SOURCE;

        Route::middleware('cms.can:catalog.view,catalog.manage')->group(static function () use ($path): void {
            Route::get($path, [LandingController::class, 'index'])->name('index');
            // Before `{landing}`: these are words, not ids.
            Route::get($path.'/facets', [LandingController::class, 'facets'])->name('facets');
            Route::post($path.'/count', [LandingController::class, 'count'])->name('count');
            Route::get($path.'/generate/{run}', [LandingController::class, 'run'])->whereNumber('run')->name('generate.run');
            Route::get($path.'/{landing}', [LandingController::class, 'show'])->whereNumber('landing')->name('show');
        });

        Route::middleware('cms.can:catalog.manage')->group(static function () use ($path): void {
            Route::post($path, [LandingController::class, 'store'])->name('store');
            Route::post($path.'/generate', [LandingController::class, 'generate'])->name('generate');
            Route::put($path.'/{landing}', [LandingController::class, 'update'])->whereNumber('landing')->name('update');
            Route::delete($path.'/{landing}', [LandingController::class, 'destroy'])->whereNumber('landing')->name('destroy');
            Route::post($path.'/{landing}/restore', [LandingController::class, 'restore'])->whereNumber('landing')->name('restore');
            Route::post($path.'/{landing}/publish', [LandingController::class, 'publish'])->whereNumber('landing')->name('publish');
            Route::post($path.'/{landing}/unpublish', [LandingController::class, 'unpublish'])->whereNumber('landing')->name('unpublish');
        });
    });
