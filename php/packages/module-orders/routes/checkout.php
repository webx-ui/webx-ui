<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Orders\Checkout\CheckoutLinks;
use WebxUi\Orders\Checkout\Http\CheckoutController;
use WebxUi\Orders\Http\KeepPrivate;

/*
 * The checkout (spec §19), loaded only while `webx-orders.checkout.enabled` is on. Twice where the
 * site puts the language in the path, as the buyer's page of an order. A service route
 * (`webx_service`): no link field offers it, and `routing` never gives its address to a page.
 * `checkout` is the name the cart's button looks for.
 */
$middleware = [...array_values(array_map('strval', (array) config('webx-orders.middleware', ['web', 'webx.locale']))), KeepPrivate::class];
$path = trim((string) config('webx-orders.checkout.path', 'checkout'), '/');

$routes = static function (string $prefix, string $suffix) use ($path): void {
    Route::get($prefix.$path, [CheckoutController::class, 'show'])->name(CheckoutLinks::PAGE.$suffix);
    Route::get($prefix.$path.'/summary', [CheckoutController::class, 'summary'])->name(CheckoutLinks::SUMMARY.$suffix);
    Route::post($prefix.$path.'/steps/{step}', [CheckoutController::class, 'step'])
        ->where('step', '[a-z][a-z0-9-]*')
        ->name(CheckoutLinks::STEP.$suffix);
    Route::post($prefix.$path.'/place', [CheckoutController::class, 'place'])->name(CheckoutLinks::PLACE.$suffix);
};

Route::group(['webx_service' => true], static function () use ($middleware, $routes): void {
    Route::middleware($middleware)->group(static fn () => $routes('', ''));

    if ((string) config('webx-localization.strategy', 'prefix') === 'prefix') {
        Route::middleware([...$middleware, OneSpellingPerAddress::class])
            ->group(static fn () => $routes('{'.OneSpellingPerAddress::PARAMETER.'}/', '.localised'));
    }
});
