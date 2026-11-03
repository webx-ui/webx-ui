<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Payments\Http\NotifyController;
use WebxUi\Payments\Online\PaymentLinks;

/*
 * The gateways' notifications (spec §4, §9.2): no language, no session, no cookies — and no
 * middleware at all (decision 49): a throttle, an auth or a CSRF check would refuse a notification
 * before the controller writes it into the journal. `NotifyRouteTest` holds the stack empty.
 */
Route::group(['webx_service' => true], static function (): void {
    Route::post(trim((string) config('webx-payments.notify.path', 'payments/notify'), '/').'/{method}', NotifyController::class)
        ->where('method', '[A-Za-z0-9_.-]{1,64}')
        ->name(PaymentLinks::NOTIFY);
});
