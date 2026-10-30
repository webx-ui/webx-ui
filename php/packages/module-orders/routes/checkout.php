<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Orders\Checkout\Checkout;
use WebxUi\Orders\Checkout\CheckoutLinks;
use WebxUi\Orders\Checkout\Http\CheckoutController;
use WebxUi\Orders\Checkout\Http\QuickController;
use WebxUi\Orders\Checkout\Http\ScriptController;
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
        ->middleware('throttle:'.Checkout::THROTTLE_STEPS)
        ->name(CheckoutLinks::STEP.$suffix);
    Route::post($prefix.$path.'/place', [CheckoutController::class, 'place'])
        ->middleware('throttle:'.Checkout::THROTTLE_PLACE)
        ->name(CheckoutLinks::PLACE.$suffix);
    Route::get($prefix.$path.'/thanks/{number}', [CheckoutController::class, 'thanks'])
        ->where('number', '[^/]+')
        ->name(CheckoutLinks::THANKS.$suffix);
    // «Buy in one click» (§29) is posted from a product's page, which may be cached whole with a
    // token minted for somebody else: no CSRF, as the inbox's intake — the antispam and the limits
    // of the controller guard it instead.
    Route::post($prefix.$path.'/quick', QuickController::class)
        ->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class])
        ->middleware('throttle:'.Checkout::THROTTLE_PLACE)
        ->name(CheckoutLinks::QUICK.$suffix);
};

// The script of the page (§27.3): no session, no language — the same file for everybody.
Route::get('webx/orders/checkout.js', ScriptController::class)->name(ScriptController::ROUTE);

Route::group(['webx_service' => true], static function () use ($middleware, $routes): void {
    Route::middleware($middleware)->group(static fn () => $routes('', ''));

    if ((string) config('webx-localization.strategy', 'prefix') === 'prefix') {
        Route::middleware([...$middleware, OneSpellingPerAddress::class])
            ->group(static fn () => $routes('{'.OneSpellingPerAddress::PARAMETER.'}/', '.localised'));
    }
});
