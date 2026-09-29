<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\Tariffs\Http\Controllers\TariffController;
use WebxUi\Tariffs\Http\Controllers\TariffRestoreController;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Models\TariffCategory;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.tariffs.panel.')
    ->group(function (): void {
        /*
         * The groups: read by anybody who may open a tariff — the form files into them — and
         * written by whoever looks after the groups (`TariffCategory::categoryKind()`). They share
         * the prefix with the tariffs themselves; `tariffs/{tariff}` takes numbers only, so the
         * two never read each other's addresses.
         */
        CategoryRoutes::register(TariffCategory::class, 'tariffs/categories');

        Route::middleware('cms.can:tariffs.view,tariffs.manage')->group(function (): void {
            Route::get('tariffs', [TariffController::class, 'index'])->name('tariffs.index');
            Route::get('tariffs/{tariff}', [TariffController::class, 'show'])->whereNumber('tariff')->name('tariffs.show');
        });

        Route::middleware('cms.can:tariffs.manage')->group(function (): void {
            Route::post('tariffs', [TariffController::class, 'store'])->name('tariffs.store');
            Route::put('tariffs/{tariff}', [TariffController::class, 'update'])->whereNumber('tariff')->name('tariffs.update');
            Route::delete('tariffs/{tariff}', [TariffController::class, 'destroy'])->whereNumber('tariff')->name('tariffs.destroy');

            // Not model-bound: the tariff this one is about is in the bin, where the binding of
            // every other route here cannot see it.
            Route::post('tariffs/{tariff}/restore', TariffRestoreController::class)
                ->whereNumber('tariff')
                ->name('tariffs.restore');
        });

        // The order, dragged the way the editor sees it: without a group the whole list, with one
        // the order inside it. The shared route fits, because a tariff has groups (§5.4).
        CategoryRoutes::items(Tariff::class, 'tariffs', 'tariffs.manage');
    });
