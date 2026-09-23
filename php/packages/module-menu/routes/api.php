<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Menu\Http\Controllers\MenuCacheController;
use WebxUi\Menu\Http\Controllers\MenuController;
use WebxUi\Menu\Http\Controllers\MenuItemController;
use WebxUi\Menu\Http\Controllers\MenuItemMoveController;

/**
 * The section's API (§10).
 *
 * A menu is addressed by its key and not by its id, because a key is what the site calls it and
 * what an administrator reads on the screen — and because a declared menu has no row until
 * somebody saves it, so for part of its life it has no id to be addressed by.
 *
 * `cache/flush` for every menu at once is written before the one for a single menu: `{key}`
 * would otherwise match the word `cache` and take the request meant for all of them.
 */
Route::prefix((string) config('webx-admin.api_path').'/menus')
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.menus.')
    ->where(['key' => '[A-Za-z0-9_-]+'])
    ->group(function (): void {
        Route::middleware('cms.can:menu.view,menu.manage')->group(function (): void {
            Route::get('/', [MenuController::class, 'index'])->name('index');
            Route::get('{key}/items', [MenuItemController::class, 'index'])->name('items.index');
        });

        Route::middleware('cms.can:menu.manage')->group(function (): void {
            Route::post('cache/flush', [MenuCacheController::class, 'all'])->name('cache.flush-all');

            Route::post('/', [MenuController::class, 'store'])->name('store');
            Route::patch('{key}', [MenuController::class, 'update'])->name('update');
            Route::delete('{key}', [MenuController::class, 'destroy'])->name('destroy');

            Route::post('{key}/cache/flush', [MenuCacheController::class, 'one'])->name('cache.flush');

            Route::post('{key}/items', [MenuItemController::class, 'store'])->name('items.store');
            Route::patch('{key}/items/{item}', [MenuItemController::class, 'update'])
                ->whereNumber('item')
                ->name('items.update');
            Route::delete('{key}/items/{item}', [MenuItemController::class, 'destroy'])
                ->whereNumber('item')
                ->name('items.destroy');

            Route::post('{key}/items/{item}/move', MenuItemMoveController::class)
                ->whereNumber('item')
                ->name('items.move');
        });
    });
