<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Orders\Http\Controllers\Panel\MassController;
use WebxUi\Orders\Http\Controllers\Panel\OrderController;
use WebxUi\Orders\Panel\OrdersModule;

/*
 * «Orders» in the panel (spec §11.1): `orders.view` reads the list and the card — the contacts
 * among them, which is personal data; `orders.manage` works an order — its status, the marks, the
 * cancellation, the contacts and the note; `orders.refund` records money given back, apart,
 * because it is money. Whoever may manage or refund may also read.
 */
Route::prefix((string) config('webx-admin.api_path').'/orders')
    ->middleware('webx.panel')
    ->name('webx.orders.panel.')
    ->group(function (): void {
        $read = 'cms.can:'.OrdersModule::VIEW.','.OrdersModule::MANAGE.','.OrdersModule::REFUND;
        $manage = 'cms.can:'.OrdersModule::MANAGE;

        Route::middleware($read)->group(function (): void {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::get('meta', [OrderController::class, 'meta'])->name('meta');
            Route::get('{order}', [OrderController::class, 'show'])->whereNumber('order')->name('show');
        });

        Route::middleware($manage)->group(function (): void {
            Route::post('status', [MassController::class, 'status'])->name('mass.status');
            Route::post('cancel', [MassController::class, 'cancel'])->name('mass.cancel');
            Route::post('{order}/status', [OrderController::class, 'status'])->whereNumber('order')->name('status');
            Route::post('{order}/milestones', [OrderController::class, 'mark'])->whereNumber('order')->name('mark');
            Route::post('{order}/cancel', [OrderController::class, 'cancel'])->whereNumber('order')->name('cancel');
            Route::put('{order}/details', [OrderController::class, 'details'])->whereNumber('order')->name('details');
        });

        Route::post('{order}/refunds', [OrderController::class, 'refund'])
            ->middleware('cms.can:'.OrdersModule::REFUND)
            ->whereNumber('order')
            ->name('refund');
    });
