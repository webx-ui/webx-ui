<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Delivery\Http\Panel\OrderChangeController;
use WebxUi\Delivery\Http\Panel\OrderDeliveryController;

/*
 * The tab «Delivery» of the order's card (§11, §14.2), loaded only where `module-orders` is: any
 * of the delivery rights reads it, `delivery.ship` makes and cancels a waybill and asks the
 * carrier where the parcel is — a manager of orders, without the delivery settings (§14.1);
 * «Change delivery» edits the order and asks what editing the order asks: `orders.manage`.
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.delivery.panel.')
    ->group(function (): void {
        Route::prefix('delivery')->group(function (): void {
            Route::middleware('cms.can:delivery.view,delivery.ship,delivery.manage')->group(function (): void {
                Route::get('orders/{order}', [OrderDeliveryController::class, 'show'])->whereNumber('order')->name('orders.show');
                Route::get('shipments/{shipment}/label', [OrderDeliveryController::class, 'label'])->whereNumber('shipment')->name('shipments.label');
            });

            Route::middleware('cms.can:delivery.ship')->group(function (): void {
                Route::post('orders/{order}/waybill', [OrderDeliveryController::class, 'store'])->whereNumber('order')->name('orders.waybill');
                Route::post('orders/{order}/track', [OrderDeliveryController::class, 'track'])->whereNumber('order')->name('orders.track');
                Route::post('shipments/{shipment}/cancel', [OrderDeliveryController::class, 'cancel'])->whereNumber('shipment')->name('shipments.cancel');
            });

            // «Change delivery»: an edit of the order, for whoever edits orders (decision of 02.11.2026).
            Route::middleware('cms.can:orders.manage')->prefix('orders/{order}/change')->whereNumber('order')->name('orders.change.')->group(function (): void {
                Route::get('localities', [OrderChangeController::class, 'localities'])->name('localities');
                Route::get('methods', [OrderChangeController::class, 'methods'])->name('methods');
                Route::get('points', [OrderChangeController::class, 'points'])->name('points');
                Route::get('streets', [OrderChangeController::class, 'streets'])->name('streets');
                Route::post('', [OrderChangeController::class, 'update'])->name('update');
            });
        });
    });
