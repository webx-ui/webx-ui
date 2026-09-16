<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Blocks\Http\Controllers\BlockController;
use WebxUi\Blocks\Http\Controllers\BlockVersionController;
use WebxUi\Blocks\Http\Controllers\PublishController;
use WebxUi\Blocks\Http\Controllers\RenderController;
use WebxUi\Blocks\Http\Controllers\UsageController;

Route::prefix((string) config('webx-admin.api_path').'/blocks')
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.blocks.')
    ->group(function (): void {
        Route::middleware('cms.can:blocks.view,blocks.manage')->group(function (): void {
            Route::get('/', [BlockController::class, 'index'])->name('index');

            // What the constructor and the picker read: the published types with their
            // schemas and thumbnails, disabled ones included so that a block already on a
            // page still has a form.
            Route::get('catalog', [BlockController::class, 'catalog'])->name('catalog');

            Route::get('{block}', [BlockController::class, 'show'])->whereNumber('block')->name('show');
            Route::get('{block}/usage', UsageController::class)->whereNumber('block')->name('usage');
            Route::get('{block}/versions', [BlockVersionController::class, 'index'])->whereNumber('block')->name('versions.index');
            Route::get('{block}/versions/{number}', [BlockVersionController::class, 'show'])->whereNumber('block')->whereNumber('number')->name('versions.show');

            // Reading, when it renders what is stored; the controller itself asks for more
            // when the request carries a template nobody has saved.
            Route::post('{block}/render', RenderController::class)->whereNumber('block')->name('render');
        });

        Route::middleware(['cms.can:blocks.manage', 'webx.blocks-editing'])->group(function (): void {
            Route::post('/', [BlockController::class, 'store'])->name('store');
            Route::put('{block}', [BlockController::class, 'update'])->whereNumber('block')->name('update');
            Route::delete('{block}', [BlockController::class, 'destroy'])->whereNumber('block')->name('destroy');
            Route::post('{block}/publish', PublishController::class)->whereNumber('block')->name('publish');
            Route::post('{block}/versions/{number}/restore', [BlockVersionController::class, 'restore'])->whereNumber('block')->whereNumber('number')->name('versions.restore');
        });
    });
