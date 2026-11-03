<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Payments\Http\Panel\AttemptController;
use WebxUi\Payments\Http\Panel\ExchangeController;
use WebxUi\Payments\Http\Panel\MethodController;
use WebxUi\Payments\Http\Panel\OrderPaymentController;

/*
 * The section «Payments» of the panel and the tab «Payment» of an order (spec §13): reading behind
 * `payments.view` — or the right to write the methods, which reads what it writes; the methods and
 * their keys behind `payments.manage`; a body of the exchange behind `payments.exchange`
 * (decision 47); an action on an order behind the order's own `orders.manage` (§13.1).
 *
 * The exchange journal has no route that changes or removes an event, for any right (decision 31).
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.payments.panel.')
    ->group(function (): void {
        Route::prefix('payments')->group(function (): void {
            Route::middleware('cms.can:payments.view,payments.manage')->group(function (): void {
                Route::get('methods', [MethodController::class, 'index'])->name('methods.index');
                Route::get('methods/blank', [MethodController::class, 'blank'])->name('methods.blank');
                Route::get('methods/{method}', [MethodController::class, 'show'])->whereNumber('method')->name('methods.show');

                Route::get('attempts', [AttemptController::class, 'index'])->name('attempts.index');
                Route::get('attempts/{payment}', [AttemptController::class, 'show'])->whereNumber('payment')->name('attempts.show');

                Route::get('exchange', [ExchangeController::class, 'index'])->name('exchange.index');
                Route::get('exchange/{event}', [ExchangeController::class, 'show'])->whereNumber('event')->name('exchange.show');

                Route::get('orders/{order}', [OrderPaymentController::class, 'show'])->whereNumber('order')->name('orders.show');
            });

            Route::middleware('cms.can:payments.exchange')->group(function (): void {
                foreach (['request', 'response'] as $part) {
                    Route::get("exchange/{event}/{$part}", [ExchangeController::class, 'body'])->whereNumber('event')->defaults('part', $part)->name("exchange.{$part}");
                }
            });

            Route::middleware('cms.can:payments.manage')->group(function (): void {
                Route::post('methods', [MethodController::class, 'store'])->name('methods.store');
                Route::put('methods/order', [MethodController::class, 'order'])->name('methods.order');
                Route::put('methods/{method}', [MethodController::class, 'update'])->whereNumber('method')->name('methods.update');
                Route::post('methods/{method}/test', [MethodController::class, 'test'])->whereNumber('method')->name('methods.test');
            });

            // Actions on an order: whoever works the orders (§13.1), not a second right for the same.
            Route::middleware('cms.can:orders.manage')->group(function (): void {
                Route::post('attempts/{payment}/check', [AttemptController::class, 'check'])->whereNumber('payment')->name('attempts.check');
                Route::post('orders/{order}/mark-paid', [OrderPaymentController::class, 'markPaid'])->whereNumber('order')->name('orders.mark-paid');
                Route::post('orders/{order}/check', [OrderPaymentController::class, 'check'])->whereNumber('order')->name('orders.check');
                Route::post('orders/{order}/method', [OrderPaymentController::class, 'switchMethod'])->whereNumber('order')->name('orders.method');
                // A hold (§9.5, decision 32): capture all or a part of it, or let it go; `dry_run` says what would be.
                Route::post('orders/{order}/attempts/{payment}/capture', [OrderPaymentController::class, 'capture'])->whereNumber('order')->whereNumber('payment')->name('orders.capture');
                Route::post('orders/{order}/attempts/{payment}/void', [OrderPaymentController::class, 'void'])->whereNumber('order')->whereNumber('payment')->name('orders.void');
                Route::post('orders/{order}/attempts/{payment}/resolve', [OrderPaymentController::class, 'resolve'])->whereNumber('order')->whereNumber('payment')->name('orders.resolve');
            });
        });
    });
