<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Blocks\Http\Controllers\BlockController;
use WebxUi\Blocks\Http\Controllers\BlockVersionController;
use WebxUi\Blocks\Http\Controllers\ComponentController;
use WebxUi\Blocks\Http\Controllers\PublishController;
use WebxUi\Blocks\Http\Controllers\RegionController;
use WebxUi\Blocks\Http\Controllers\RenderController;
use WebxUi\Blocks\Http\Controllers\ReorderController;
use WebxUi\Blocks\Http\Controllers\UsageController;

Route::prefix((string) config('webx-admin.api_path').'/blocks')
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.blocks.')
    ->group(function (): void {
        // What the constructor and the picker read, and what draws one block into the preview —
        // for the editor of a region too, who picks and previews blocks without being let into
        // the section where types are made (§7.1 of the regions spec).
        Route::middleware('cms.can:blocks.view,blocks.manage,blocks.regions')->group(function (): void {
            // The published types with their schemas and thumbnails, disabled ones included so
            // that a block already on a page still has a form.
            Route::get('catalog', [BlockController::class, 'catalog'])->name('catalog');

            // Reading, when it renders what is stored; the controller itself asks for more
            // when the request carries a template nobody has saved.
            Route::post('{block}/render', RenderController::class)->whereNumber('block')->name('render');
        });

        Route::middleware('cms.can:blocks.view,blocks.manage')->group(function (): void {
            Route::get('/', [BlockController::class, 'index'])->name('index');
            Route::get('{block}', [BlockController::class, 'show'])->whereNumber('block')->name('show');
            Route::get('{block}/usage', UsageController::class)->whereNumber('block')->name('usage');
            Route::get('{block}/versions', [BlockVersionController::class, 'index'])->whereNumber('block')->name('versions.index');
            Route::get('{block}/versions/{number}', [BlockVersionController::class, 'show'])->whereNumber('block')->whereNumber('number')->name('versions.show');
        });

        Route::middleware(['cms.can:blocks.manage', 'webx.blocks-editing'])->group(function (): void {
            Route::post('/', [BlockController::class, 'store'])->name('store');
            // The order of the list and the picker; never the order of the styles (`sort`).
            Route::post('reorder', ReorderController::class)->name('reorder');
            Route::put('{block}', [BlockController::class, 'update'])->whereNumber('block')->name('update');
            Route::delete('{block}', [BlockController::class, 'destroy'])->whereNumber('block')->name('destroy');
            Route::post('{block}/publish', PublishController::class)->whereNumber('block')->name('publish');
            // A component of a slug a module declared, started from the module's view (§4.2 of
            // the components spec). A draft: the site keeps the view until it is published.
            Route::post('components/{slug}/customise', [ComponentController::class, 'customise'])->where('slug', '[a-z][a-z0-9-]*')->name('components.customise');
            Route::post('{block}/versions/{number}/restore', [BlockVersionController::class, 'restore'])->whereNumber('block')->whereNumber('number')->name('versions.restore');
        });
    });

// The regions of the layout (§7 of the regions spec): content, so edited by whoever holds
// `blocks.regions` — not behind `blocks.manage` and not behind `webx-blocks.editing`, which is
// about writing Blade. Addressed by the name the layout's tag uses.
Route::prefix((string) config('webx-admin.api_path').'/regions')
    ->middleware(['web', 'webx.panel-locale', 'cms.auth', 'cms.can:blocks.regions'])
    ->name('webx.blocks.regions.')
    ->group(function (): void {
        $name = '[a-z][a-z0-9-]*';

        Route::get('/', [RegionController::class, 'index'])->name('index');
        Route::get('{name}', [RegionController::class, 'show'])->where('name', $name)->name('show');
        Route::put('{name}', [RegionController::class, 'update'])->where('name', $name)->name('update');
        Route::post('{name}/publish', [RegionController::class, 'publish'])->where('name', $name)->name('publish');
        Route::post('{name}/unpublish', [RegionController::class, 'unpublish'])->where('name', $name)->name('unpublish');
        Route::delete('{name}/draft', [RegionController::class, 'discard'])->where('name', $name)->name('discard');
        Route::get('{name}/versions', [RegionController::class, 'versions'])->where('name', $name)->name('versions');
        Route::post('{name}/versions/{number}/restore', [RegionController::class, 'restore'])->where('name', $name)->whereNumber('number')->name('versions.restore');
        // Writes a block type as well: the controller asks for `blocks.manage` and editing on top.
        Route::post('{name}/adopt', [RegionController::class, 'adopt'])->where('name', $name)->name('adopt');
    });
