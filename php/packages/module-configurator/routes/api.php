<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Configurator\Http\Controllers\ConfiguratorCheckController;
use WebxUi\Configurator\Http\Controllers\ConfiguratorController;
use WebxUi\Configurator\Http\Controllers\ConfiguratorPublicationController;
use WebxUi\Configurator\Http\Controllers\ConfiguratorVersionController;

/*
 * The panel's API of the section (§13.3). Reading — the list, the record, its history, a check
 * and a price — behind either permission; everything that writes behind `configurators.manage`,
 * publishing included, as for pages (decision 12).
 */
Route::prefix((string) config('webx-admin.api_path').'/configurators')
    ->middleware('webx.panel')
    ->name('webx.configurators.')
    ->group(function (): void {
        Route::middleware('cms.can:configurators.view,configurators.manage')->group(function (): void {
            Route::get('/', [ConfiguratorController::class, 'index'])->name('index');
            Route::get('{configurator}', [ConfiguratorController::class, 'show'])->whereNumber('configurator')->name('show');
            Route::get('{configurator}/versions', [ConfiguratorVersionController::class, 'index'])->whereNumber('configurator')->name('versions');

            // Before `{configurator}`: `validate` is a word.
            Route::post('validate', [ConfiguratorCheckController::class, 'validate'])->name('validate');
            Route::post('{configurator}/quote', [ConfiguratorCheckController::class, 'quote'])->whereNumber('configurator')->name('quote');
        });

        Route::middleware('cms.can:configurators.manage')->group(function (): void {
            Route::post('/', [ConfiguratorController::class, 'store'])->name('store');
            Route::put('{configurator}', [ConfiguratorController::class, 'update'])->whereNumber('configurator')->name('update');
            Route::delete('{configurator}', [ConfiguratorController::class, 'destroy'])->whereNumber('configurator')->name('destroy');

            Route::post('{configurator}/publish', [ConfiguratorPublicationController::class, 'publish'])->whereNumber('configurator')->name('publish');
            Route::post('{configurator}/unpublish', [ConfiguratorPublicationController::class, 'unpublish'])->whereNumber('configurator')->name('unpublish');
            Route::post('{configurator}/discard', [ConfiguratorPublicationController::class, 'discard'])->whereNumber('configurator')->name('discard');

            Route::post('{configurator}/versions/{number}/restore', [ConfiguratorVersionController::class, 'restore'])
                ->whereNumber('configurator')
                ->whereNumber('number')
                ->name('versions.restore');

            // Not model-bound: a configurator in the bin is invisible to the binding.
            Route::post('{configurator}/restore', [ConfiguratorController::class, 'restore'])->whereNumber('configurator')->name('restore');
        });
    });
