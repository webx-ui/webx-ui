<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Delivery\Http\Panel\BindingController;
use WebxUi\Delivery\Http\Panel\CarrierController;
use WebxUi\Delivery\Http\Panel\GeographyController;
use WebxUi\Delivery\Http\Panel\MethodController;
use WebxUi\Delivery\Http\Panel\PointController;
use WebxUi\Delivery\Http\Panel\PopularController;

/*
 * The section «Delivery» of the panel (spec §14): reading behind `delivery.view` — or the right
 * to write, which reads what it writes — and every write behind `delivery.manage`. The geography
 * is written here too (decision 42): a town is made for a point or a courier by whoever keeps them.
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.delivery.panel.')
    ->group(function (): void {
        Route::prefix('delivery')->group(function (): void {
            Route::middleware('cms.can:delivery.view,delivery.manage')->group(function (): void {
                Route::get('methods', [MethodController::class, 'index'])->name('methods.index');
                Route::get('methods/blank', [MethodController::class, 'blank'])->name('methods.blank');
                Route::get('methods/{method}', [MethodController::class, 'show'])->whereNumber('method')->name('methods.show');

                Route::get('points', [PointController::class, 'index'])->name('points.index');
                Route::get('points/{point}', [PointController::class, 'show'])->whereNumber('point')->name('points.show');

                Route::get('carriers', [CarrierController::class, 'index'])->name('carriers.index');
                Route::get('carriers/{carrier}', [CarrierController::class, 'show'])->where('carrier', '[a-z0-9-]+')->name('carriers.show');

                Route::get('geography/countries', [GeographyController::class, 'countries'])->name('countries.index');
                Route::get('geography/regions', [GeographyController::class, 'regions'])->name('regions.index');
                Route::get('geography/localities', [GeographyController::class, 'localities'])->name('localities.index');
                Route::get('geography/localities/suggest', [GeographyController::class, 'suggest'])->name('localities.suggest');
                Route::get('geography/localities/brief', [GeographyController::class, 'brief'])->name('localities.brief');
                Route::get('geography/localities/{locality}', [GeographyController::class, 'locality'])->whereNumber('locality')->name('localities.show');

                Route::get('popular', [PopularController::class, 'index'])->name('popular.index');
                Route::get('bindings', [BindingController::class, 'index'])->name('bindings.index');
            });

            Route::middleware('cms.can:delivery.manage')->group(function (): void {
                Route::post('methods', [MethodController::class, 'store'])->name('methods.store');
                Route::put('methods/order', [MethodController::class, 'order'])->name('methods.order');
                Route::post('methods/point-kinds', [MethodController::class, 'defineKind'])->name('methods.point-kinds');
                Route::put('methods/{method}', [MethodController::class, 'update'])->whereNumber('method')->name('methods.update');
                Route::delete('methods/{method}', [MethodController::class, 'destroy'])->whereNumber('method')->name('methods.destroy');

                Route::post('points', [PointController::class, 'store'])->name('points.store');
                Route::put('points/{point}', [PointController::class, 'update'])->whereNumber('point')->name('points.update');
                Route::delete('points/{point}', [PointController::class, 'destroy'])->whereNumber('point')->name('points.destroy');

                Route::put('carriers/{carrier}', [CarrierController::class, 'update'])->where('carrier', '[a-z0-9-]+')->name('carriers.update');
                // Options from the carrier's account hold its contacts and phones: not for a reader (§5.5).
                Route::get('carriers/{carrier}/options/{field}', [CarrierController::class, 'options'])->where('carrier', '[a-z0-9-]+')->where('field', '[A-Za-z0-9_]+')->name('carriers.options');
                Route::post('carriers/{carrier}/test', [CarrierController::class, 'test'])->where('carrier', '[a-z0-9-]+')->name('carriers.test');
                Route::post('carriers/{carrier}/sync/{directory}', [CarrierController::class, 'sync'])->where('carrier', '[a-z0-9-]+')->where('directory', '[a-z]+')->name('carriers.sync');
                Route::post('carriers/{carrier}/confirm/{directory}', [CarrierController::class, 'confirm'])->where('carrier', '[a-z0-9-]+')->where('directory', '[a-z]+')->name('carriers.confirm');

                Route::post('geography/countries', [GeographyController::class, 'enableCountry'])->name('countries.enable');
                Route::put('geography/countries/order', [GeographyController::class, 'orderCountries'])->name('countries.order');
                Route::delete('geography/countries/{code}', [GeographyController::class, 'disableCountry'])->where('code', '[A-Za-z]+')->name('countries.disable');
                Route::post('geography/countries/{code}/remove', [GeographyController::class, 'removeCountry'])->where('code', '[A-Za-z]+')->name('countries.remove');
                Route::put('geography/countries/{code}/leader', [GeographyController::class, 'lead'])->where('code', '[A-Za-z]+')->name('countries.leader');

                Route::post('geography/regions', [GeographyController::class, 'storeRegion'])->name('regions.store');
                Route::put('geography/regions/order', [GeographyController::class, 'orderRegions'])->name('regions.order');
                Route::put('geography/regions/{region}', [GeographyController::class, 'updateRegion'])->whereNumber('region')->name('regions.update');
                Route::delete('geography/regions/{region}', [GeographyController::class, 'destroyRegion'])->whereNumber('region')->name('regions.destroy');

                Route::post('geography/localities', [GeographyController::class, 'storeLocality'])->name('localities.store');
                Route::put('geography/localities/{locality}', [GeographyController::class, 'updateLocality'])->whereNumber('locality')->name('localities.update');
                Route::delete('geography/localities/{locality}', [GeographyController::class, 'destroyLocality'])->whereNumber('locality')->name('localities.destroy');
                Route::post('geography/localities/{locality}/activate', [GeographyController::class, 'activate'])->whereNumber('locality')->name('localities.activate');
                Route::post('geography/localities/{locality}/deactivate', [GeographyController::class, 'deactivate'])->whereNumber('locality')->name('localities.deactivate');

                Route::put('popular', [PopularController::class, 'update'])->name('popular.update');
                Route::post('popular', [PopularController::class, 'store'])->name('popular.store');
                Route::delete('popular/{locality}', [PopularController::class, 'destroy'])->whereNumber('locality')->name('popular.destroy');

                Route::post('bindings/batch', [BindingController::class, 'batch'])->name('bindings.batch');
            });
        });
    });
