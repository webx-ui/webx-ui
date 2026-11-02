<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Delivery\Http\Panel\OrderDeliveryController;

/*
 * The tab «Delivery» of the order's card (§11, §14.2), loaded only where `module-orders` is: any
 * of the delivery rights reads it, `delivery.ship` makes and cancels a waybill and asks the
 * carrier where the parcel is — a manager of orders, without the delivery settings (§14.1).
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
        });
    });
