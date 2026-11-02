<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Cart\Http\KeepPrivate;
use WebxUi\Delivery\Http\DeliveryController;
use WebxUi\Delivery\Http\ScriptController;

/*
 * The JSON API of the delivery step (spec §12.2). Two halves:
 *
 * - what is the same for everybody — countries, localities, popular towns, points, streets — needs
 *   no session and no cookie: outside the `web` group, cached by the browser for five minutes,
 *   throttled per IP (`webx-delivery`, 60 a minute);
 * - what is one visitor's — the methods priced for their cart and their choice — in the `web`
 *   group: the cart's cookie is encrypted, CSRF guards the write, and a form without JavaScript
 *   comes back through the session. No cache keeps it (`KeepPrivate` of the cart).
 *
 * `webx_service`: none of them is a page a visitor opens.
 */
$path = trim((string) config('webx-delivery.path', 'api/delivery'), '/');

Route::group(['webx_service' => true], static function () use ($path): void {
    Route::prefix($path)
        ->name('webx-delivery.')
        ->middleware('throttle:webx-delivery')
        ->group(static function (): void {
            Route::get('countries', [DeliveryController::class, 'countries'])->name('countries');
            Route::get('localities', [DeliveryController::class, 'localities'])->name('localities');
            Route::get('localities/popular', [DeliveryController::class, 'popular'])->name('localities.popular');
            Route::get('points', [DeliveryController::class, 'points'])->name('points');
            Route::get('points/{id}', [DeliveryController::class, 'point'])->whereNumber('id')->name('points.show');
            Route::get('streets', [DeliveryController::class, 'streets'])->name('streets');
        });

    Route::middleware(['web', KeepPrivate::class])
        ->prefix($path)
        ->name('webx-delivery.')
        ->group(static function (): void {
            Route::get('methods', [DeliveryController::class, 'methods'])->name('methods');
            Route::get('selection', [DeliveryController::class, 'selection'])->name('selection');
            Route::put('selection', [DeliveryController::class, 'update'])->middleware('throttle:webx-delivery-write')->name('selection.update');
        });

    // The step's script: no session, cached forever — its address carries a hash of it.
    Route::get(trim((string) config('webx-delivery.script_path', 'webx/delivery'), '/').'/delivery.js', ScriptController::class)->name('webx-delivery.script');
});
