<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Cart\Http\KeepPrivate;
use WebxUi\Payments\Http\MarkController;
use WebxUi\Payments\Http\PayController;
use WebxUi\Payments\Http\ScriptController;
use WebxUi\Payments\Http\StepController;

/*
 * The JSON API of the payment step (spec §10.2): the visitor's own methods and choice, in the
 * `web` group — the cart's cookie is encrypted, CSRF guards the write, a form without JavaScript
 * comes back through the session. No cache keeps it (`KeepPrivate` of the cart).
 *
 * `webx_service`: none of them is a page a visitor opens.
 */
$path = trim((string) config('webx-payments.api_path', 'api/payments'), '/');

Route::group(['webx_service' => true], static function () use ($path): void {
    Route::middleware(['web', KeepPrivate::class])
        ->prefix($path)
        ->name('webx-payments.')
        ->group(static function (): void {
            Route::get('methods', [StepController::class, 'methods'])->name('methods');
            // The page «we are checking» asks here (§8.4): the attempt's row, the gateway at most every 15 s.
            Route::get('{uuid}/status', [PayController::class, 'status'])->where('uuid', '[0-9A-Za-z]{26}')->name('status');
            Route::put('selection', [StepController::class, 'update'])->middleware('throttle:webx-payments-write')->name('selection.update');
            Route::delete('selection', [StepController::class, 'destroy'])->middleware('throttle:webx-payments-write')->name('selection.destroy');
        });

    // The step's script and the files of the marks: no session, cached forever — their addresses
    // carry a hash of them.
    $files = trim((string) config('webx-payments.script_path', 'webx/payments'), '/');

    Route::get($files.'/step.js', ScriptController::class)->name('webx-payments.script');
    Route::get($files.'/methods.js', [ScriptController::class, 'methods'])->name('webx-payments.methods-script');
    Route::get($files.'/marks/{key}.svg', MarkController::class)->where('key', '[a-z0-9-]+')->name('webx-payments.mark');
});
