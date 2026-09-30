<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogProperties\Http\PropertyController;
use WebxUi\CatalogProperties\Http\SetController;
use WebxUi\CatalogProperties\Http\ValueController;
use WebxUi\CatalogProperties\Models\PropertyGroup;

/*
 * The properties (§7.3 of the properties spec): read by anybody who may open the catalogue — the
 * product form chooses from them — and written with `catalog.manage` (decision 19).
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.catalog-properties.panel.')
    ->group(function (): void {
        CategoryRoutes::register(PropertyGroup::class, CatalogPropertiesServiceProvider::GROUPS);

        $path = CatalogPropertiesServiceProvider::SOURCE;

        Route::middleware('cms.can:catalog.view,catalog.manage')->group(static function () use ($path): void {
            Route::get($path, [PropertyController::class, 'index'])->name('index');
            Route::get($path.'/{property}', [PropertyController::class, 'show'])->whereNumber('property')->name('show');
            Route::get($path.'/{property}/values', [ValueController::class, 'index'])->whereNumber('property')->name('values.index');
            Route::get('catalog/categories/{category}/properties', [SetController::class, 'show'])->whereNumber('category')->name('sets.show');
            Route::get('catalog/property-sets/{category}', [SetController::class, 'effective'])->whereNumber('category')->name('sets.effective');
        });

        Route::middleware('cms.can:catalog.manage')->group(static function () use ($path): void {
            Route::post($path, [PropertyController::class, 'store'])->name('store');
            // Before `{property}`: `reorder` is a word, not an id.
            Route::post($path.'/reorder', [PropertyController::class, 'reorder'])->name('reorder');
            Route::put($path.'/{property}', [PropertyController::class, 'update'])->whereNumber('property')->name('update');
            Route::delete($path.'/{property}', [PropertyController::class, 'destroy'])->whereNumber('property')->name('destroy');
            Route::post($path.'/{property}/restore', [PropertyController::class, 'restore'])->whereNumber('property')->name('restore');
            Route::put($path.'/{property}/intervals', [PropertyController::class, 'intervals'])->whereNumber('property')->name('intervals');

            Route::post($path.'/{property}/values', [ValueController::class, 'store'])->whereNumber('property')->name('values.store');
            Route::put($path.'/{property}/values/{value}', [ValueController::class, 'update'])->where(['property' => '[0-9]+', 'value' => '[0-9]+'])->name('values.update');
            Route::post($path.'/{property}/values/{value}/move', [ValueController::class, 'move'])->where(['property' => '[0-9]+', 'value' => '[0-9]+'])->name('values.move');
            Route::post($path.'/{property}/values/{value}/merge', [ValueController::class, 'merge'])->where(['property' => '[0-9]+', 'value' => '[0-9]+'])->name('values.merge');
            Route::delete($path.'/{property}/values/{value}', [ValueController::class, 'destroy'])->where(['property' => '[0-9]+', 'value' => '[0-9]+'])->name('values.destroy');

            Route::put('catalog/categories/{category}/properties', [SetController::class, 'update'])->whereNumber('category')->name('sets.update');
        });
    });
