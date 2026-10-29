<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Orders\Http\Controllers\Site\AccountOrdersController;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Users\Account\AccountRoutes;

/*
 * «My orders» (spec §13.2), loaded only where `webx-ui/module-users` is installed: under the
 * account's prefix and middleware, behind `webx.visitor`, with the copy behind the language prefix
 * — `AccountRoutes` puts all of that around them.
 */
AccountRoutes::group(static function (): void {
    Route::get('orders', [AccountOrdersController::class, 'index'])->name(OrderLinks::ACCOUNT);
    Route::get('orders/{number}', [AccountOrdersController::class, 'show'])->name(OrderLinks::ACCOUNT_ORDER);
});
