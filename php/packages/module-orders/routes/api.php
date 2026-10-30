<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Orders\Http\Controllers\Panel\ItemsController;
use WebxUi\Orders\Http\Controllers\Panel\MassController;
use WebxUi\Orders\Http\Controllers\Panel\NewOrderController;
use WebxUi\Orders\Http\Controllers\Panel\OrderController;
use WebxUi\Orders\Http\Controllers\Panel\SettingsController;
use WebxUi\Orders\Http\Controllers\Panel\StatusController;
use WebxUi\Orders\Http\Controllers\Panel\VisitorController;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Orders\Site\VisitorOrders;

/*
 * «Orders» in the panel (spec §11.1): `orders.view` reads the list and the card — the contacts
 * among them, which is personal data; `orders.manage` works an order — its status, the marks, the
 * cancellation, the contacts and the note; `orders.refund` records money given back, apart,
 * because it is money. Whoever may manage or refund may also read. The statuses and the settings
 * of the section are `orders.statuses` alone (§11.4): naming the shop's stages is not working an
 * order; the tab «Checkout» of the settings is `orders.checkout.settings` (§31.1). An order taken by
 * phone (§29.2) is `orders.manage`: its form finds products and counts them, then places it; so is
 * an edit of the lines (§42.1, decision 101) — the same manager who takes the order by phone. The visitor of an order (§13.3) is `orders.manage`, and only with the visitors installed;
 * finding one is reading the customers, so `users.view` as well.
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
            Route::get('summary', [OrderController::class, 'summary'])->name('summary');
            Route::get('{order}/glance', [OrderController::class, 'glance'])->whereNumber('order')->name('glance');
            Route::get('{order}', [OrderController::class, 'show'])->whereNumber('order')->name('show');
        });

        Route::middleware($manage)->group(function (): void {
            Route::get('new', [NewOrderController::class, 'meta'])->name('new.meta');
            Route::get('new/products', [NewOrderController::class, 'products'])->name('new.products');
            Route::post('new/preview', [NewOrderController::class, 'preview'])->name('new.preview');
            Route::post('new', [NewOrderController::class, 'store'])->name('new.store');
            Route::post('status', [MassController::class, 'status'])->name('mass.status');
            Route::post('cancel', [MassController::class, 'cancel'])->name('mass.cancel');
            Route::post('{order}/status', [OrderController::class, 'status'])->whereNumber('order')->name('status');
            Route::post('{order}/milestones', [OrderController::class, 'mark'])->whereNumber('order')->name('mark');
            Route::post('{order}/payments', [OrderController::class, 'payment'])->whereNumber('order')->name('payment');
            // «Change the lines» (§42.1): the live totals are a dry run, the save is the edit.
            Route::post('{order}/items/preview', [ItemsController::class, 'preview'])->whereNumber('order')->name('items.preview');
            Route::put('{order}/items', [ItemsController::class, 'update'])->whereNumber('order')->name('items.update');
            Route::post('{order}/cancel', [OrderController::class, 'cancel'])->whereNumber('order')->name('cancel');
            Route::post('{order}/notes', [OrderController::class, 'note'])->whereNumber('order')->name('note');
            Route::put('{order}/details', [OrderController::class, 'details'])->whereNumber('order')->name('details');
            // A message sent again by hand (decision 99): only with the notifications module, refused otherwise.
            Route::get('{order}/notify', [OrderController::class, 'notifyMeta'])->whereNumber('order')->name('notify.meta');
            Route::post('{order}/notify', [OrderController::class, 'notify'])->whereNumber('order')->name('notify');
            Route::post('{order}/deliveries/{delivery}/resend', [OrderController::class, 'resend'])->whereNumber(['order', 'delivery'])->name('resend');

            if (VisitorOrders::installed()) {
                Route::get('{order}/visitors', [VisitorController::class, 'search'])->middleware('cms.can:users.view')->whereNumber('order')->name('visitor.search');
                Route::post('{order}/visitor', [VisitorController::class, 'link'])->whereNumber('order')->name('visitor.link');
                Route::delete('{order}/visitor', [VisitorController::class, 'unlink'])->whereNumber('order')->name('visitor.unlink');
            }
        });

        Route::middleware('cms.can:'.OrdersModule::STATUSES)->group(function (): void {
            Route::get('statuses', [StatusController::class, 'index'])->name('statuses.index');
            Route::put('statuses/arrange', [StatusController::class, 'arrange'])->name('statuses.arrange');
            Route::post('statuses/public', [StatusController::class, 'storePublic'])->name('statuses.public.store');
            Route::put('statuses/public/{status}', [StatusController::class, 'updatePublic'])->whereNumber('status')->name('statuses.public.update');
            Route::delete('statuses/public/{status}', [StatusController::class, 'destroyPublic'])->whereNumber('status')->name('statuses.public.destroy');
            Route::post('statuses/public/{status}/restore', [StatusController::class, 'restorePublic'])->whereNumber('status')->name('statuses.public.restore');
            Route::post('statuses/internal', [StatusController::class, 'store'])->name('statuses.store');
            Route::put('statuses/internal/{status}', [StatusController::class, 'update'])->whereNumber('status')->name('statuses.update');
            Route::delete('statuses/internal/{status}', [StatusController::class, 'destroy'])->whereNumber('status')->name('statuses.destroy');
            Route::post('statuses/internal/{status}/restore', [StatusController::class, 'restore'])->whereNumber('status')->name('statuses.restore');
        });

        // The settings screen holds both tabs; each field stands behind its tab's `can`.
        Route::middleware('cms.can:'.OrdersModule::STATUSES.','.OrdersModule::CHECKOUT_SETTINGS)->group(function (): void {
            Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        });
        Route::post('{order}/refunds', [OrderController::class, 'refund'])
            ->middleware('cms.can:'.OrdersModule::REFUND)
            ->whereNumber('order')
            ->name('refund');
        Route::post('{order}/refunds/{refund}/reverse', [OrderController::class, 'reverseRefund'])
            ->middleware('cms.can:'.OrdersModule::REFUND)
            ->whereNumber(['order', 'refund'])
            ->name('refund.reverse');
    });
