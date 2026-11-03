<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Orders\Http\KeepPrivate;
use WebxUi\Payments\Http\PayController;
use WebxUi\Payments\Online\PaymentLinks;

/*
 * The buyer's online payment (spec §4, §8): the page of payment of an order behind its signed link
 * — twice where the site puts the language in the path, like the order's own page — and the step
 * to the gateway and the way back by the attempt's uuid, without a language: the gateway's address is the same in
 * every one. Service routes (`webx_service`); `KeepPrivate` — nobody caches them.
 */
$middleware = [...array_values(array_map('strval', (array) config('webx-orders.middleware', ['web', 'webx.locale']))), KeepPrivate::class];
$path = trim((string) config('webx-payments.path', 'pay'), '/');

Route::group(['webx_service' => true], static function () use ($middleware, $path): void {
    // Before {number}: «go» is never an order's number, but the router takes the first that fits.
    Route::middleware(['web', KeepPrivate::class])
        ->get($path.'/go/{uuid}', [PayController::class, 'go'])
        ->where('uuid', '[0-9A-Za-z]{26}')
        ->name(PaymentLinks::GO);

    // The way back from the gateway (§8.3): one address for success and failure, without a
    // language — the gateway keeps it. POST too, without CSRF: some gateways return by a form.
    Route::middleware(['web', KeepPrivate::class])
        ->match(['GET', 'POST'], $path.'/return/{uuid}', [PayController::class, 'back'])
        ->where('uuid', '[0-9A-Za-z]{26}')
        ->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class])
        ->name(PaymentLinks::RETURN);

    $pages = static function (string $prefix, string $suffix) use ($path): void {
        Route::get($prefix.$path.'/{number}', [PayController::class, 'show'])->name(PaymentLinks::PAGE.$suffix);
        Route::post($prefix.$path.'/{number}/attempts', [PayController::class, 'attempt'])
            ->middleware('throttle:webx-payments-write')
            ->name(PaymentLinks::ATTEMPTS.$suffix);
        Route::post($prefix.$path.'/{number}/method', [PayController::class, 'method'])
            ->middleware('throttle:webx-payments-write')
            ->name(PaymentLinks::METHOD.$suffix);
    };

    Route::middleware($middleware)->group(static fn () => $pages('', ''));

    if ((string) config('webx-localization.strategy', 'prefix') === 'prefix') {
        Route::middleware([...$middleware, OneSpellingPerAddress::class])
            ->group(static fn () => $pages('{'.OneSpellingPerAddress::PARAMETER.'}/', '.localised'));
    }
});
