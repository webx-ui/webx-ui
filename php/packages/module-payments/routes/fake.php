<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use WebxUi\Orders\Http\KeepPrivate;
use WebxUi\Payments\Http\FakeGatewayController;
use WebxUi\Payments\Testing\FakeGatewaySite;

/*
 * The page of «gateway A» (spec §21.2) — a stand-in for a gateway's own site, so not under the
 * site's language. The controller answers 404 unless the fake gateway is on and the site is not
 * in production. The buttons and the handed-off form come from the «gateway's» page or the shop's
 * handoff, as a real gateway's would: no CSRF of the site.
 */
$path = trim((string) config('webx-payments.path', 'pay'), '/');

Route::group(['webx_service' => true], static function () use ($path): void {
    Route::middleware(['web', KeepPrivate::class])->group(static function () use ($path): void {
        Route::get($path.'/fake/{uuid}', [FakeGatewayController::class, 'show'])
            ->where('uuid', '[0-9A-Za-z]{26}')
            ->name(FakeGatewaySite::PAGE);
        Route::post($path.'/fake/{uuid}/{action}', [FakeGatewayController::class, 'act'])
            ->where('uuid', '[0-9A-Za-z]{26}')
            ->where('action', 'open|'.implode('|', FakeGatewayController::ACTIONS))
            ->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class])
            ->name(FakeGatewaySite::ACTION);
    });
});
