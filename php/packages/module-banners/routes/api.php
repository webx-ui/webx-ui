<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Banners\Http\Controllers\BannerController;
use WebxUi\Banners\Http\Controllers\PlaceController;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.banners.panel.')
    ->group(function (): void {
        // A place is addressed by its key: a declared one has no id until its first banner.
        $key = '[a-z][a-z0-9-]*';

        Route::middleware('cms.can:banners.view,banners.manage')->group(function () use ($key): void {
            Route::get('banners/places', [PlaceController::class, 'index'])->name('places.index');
            Route::get('banners/places/{key}/banners', [PlaceController::class, 'banners'])->where(['key' => $key])->name('places.banners');
            Route::get('banners/{banner}', [BannerController::class, 'show'])->whereNumber('banner')->name('banners.show');
        });

        Route::middleware('cms.can:banners.manage')->group(function () use ($key): void {
            Route::post('banners/places', [PlaceController::class, 'store'])->name('places.store');
            Route::put('banners/places/{key}', [PlaceController::class, 'update'])->where(['key' => $key])->name('places.update');
            Route::delete('banners/places/{key}', [PlaceController::class, 'destroy'])->where(['key' => $key])->name('places.destroy');
            Route::post('banners/places/{key}/banners', [PlaceController::class, 'storeBanner'])->where(['key' => $key])->name('places.banners.store');
            Route::post('banners/places/{key}/reorder', [PlaceController::class, 'reorder'])->where(['key' => $key])->name('places.reorder');

            Route::put('banners/{banner}', [BannerController::class, 'update'])->whereNumber('banner')->name('banners.update');
            Route::delete('banners/{banner}', [BannerController::class, 'destroy'])->whereNumber('banner')->name('banners.destroy');

            // Not model-bound: the banner this one is about is in the bin, where the binding of
            // every other route here cannot see it.
            Route::post('banners/{banner}/restore', [BannerController::class, 'restore'])->whereNumber('banner')->name('banners.restore');
        });
    });
