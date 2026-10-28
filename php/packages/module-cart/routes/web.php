<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Cart\Http\CartController;
use WebxUi\Cart\Http\KeepPrivate;
use WebxUi\Cart\Http\ScriptController;

/*
 * The cart's JSON API (spec §6). The `web` group: the token lives in an encrypted cookie, a form
 * without JavaScript comes back through the session, and CSRF guards every write. Writes are
 * throttled per IP; reading is not — it writes nothing.
 *
 * `webx_service`: none of them is a page a visitor opens, so a link field of the panel does not
 * offer them as an address of the site (`WebxUi\Admin\Links\SiteRoutes::SERVICE`). Being routes,
 * `routing` never gives the address to a page either (`Reserved` asks the router).
 */
Route::group(['webx_service' => true], static function (): void {
    Route::middleware(['web', KeepPrivate::class])
        ->prefix(trim((string) config('webx-cart.path', 'api/cart'), '/'))
        ->name('webx-cart.')
        ->group(static function (): void {
            Route::get('/', [CartController::class, 'show'])->name('show');

            Route::middleware('throttle:webx-cart')->group(static function (): void {
                Route::delete('/', [CartController::class, 'clear'])->name('clear');
                Route::post('items', [CartController::class, 'store'])->name('items.store');
                Route::patch('items/{id}', [CartController::class, 'update'])->whereNumber('id')->name('items.update');
                // A stored line by its number, a generated one by its key: `rule#12:0`.
                Route::delete('items/{id}', [CartController::class, 'destroy'])->where('id', '[^/]+')->name('items.destroy');
            });
        });
});

// The widget's script (§9.4): outside the web group — a script needs no session, and a cookie on it
// would only stop a proxy from sharing what is meant to be shared.
Route::group(['webx_service' => true], static function (): void {
    Route::get(trim((string) config('webx-cart.script_path', 'webx/cart'), '/').'/cart.js', ScriptController::class)->name('webx-cart.script');
});
