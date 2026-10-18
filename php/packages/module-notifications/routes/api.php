<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Notifications\Http\Controllers\ChannelController;
use WebxUi\Notifications\Http\Controllers\DeliveryController;
use WebxUi\Notifications\Http\Controllers\ToolController;
use WebxUi\Notifications\Panel\NotificationsModule;

/*
 * Two permissions (§11): `notifications.view` reads the channels and the log — with what visitors
 * typed into forms — and `notifications.manage` writes, tests, searches and retries. Whoever may
 * manage may also read. No route answers with a secret.
 */
Route::prefix((string) config('webx-admin.api_path').'/notifications')
    ->middleware('webx.panel')
    ->name('webx.notifications.')
    ->group(function (): void {
        Route::middleware('cms.can:'.NotificationsModule::VIEW.','.NotificationsModule::MANAGE)->group(function (): void {
            Route::get('meta', [ToolController::class, 'meta'])->name('meta');
            Route::get('channels', [ChannelController::class, 'index'])->name('channels.index');
            Route::get('channels/{channel}', [ChannelController::class, 'show'])->whereNumber('channel')->name('channels.show');
            Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
            // A preview sends nothing and saves nothing: it reads the channel, so it is a reader's,
            // the same as the agent's `notifications_preview` (§14.3 of the permissions spec). A POST
            // only because a template not saved yet travels in the body.
            Route::post('channels/{channel}/preview', [ToolController::class, 'preview'])->whereNumber('channel')->name('channels.preview');
            Route::post('preview', [ToolController::class, 'preview'])->name('preview');
        });

        Route::middleware('cms.can:'.NotificationsModule::MANAGE)->group(function (): void {
            Route::post('channels', [ChannelController::class, 'store'])->name('channels.store');
            Route::put('channels/{channel}', [ChannelController::class, 'update'])->whereNumber('channel')->name('channels.update');
            Route::delete('channels/{channel}', [ChannelController::class, 'destroy'])->whereNumber('channel')->name('channels.destroy');
            Route::post('channels/{channel}/restore', [ChannelController::class, 'restore'])->whereNumber('channel')->name('channels.restore');
            Route::delete('channels/{channel}/purge', [ChannelController::class, 'purge'])->whereNumber('channel')->name('channels.purge');
            Route::post('channels/{channel}/copy', [ChannelController::class, 'copy'])->whereNumber('channel')->name('channels.copy');
            Route::post('channels/{channel}/test', [ToolController::class, 'test'])->whereNumber('channel')->name('channels.test');
            Route::post('test', [ToolController::class, 'test'])->name('test');
            Route::post('check', [ToolController::class, 'check'])->name('check');
            Route::post('find-recipients', [ToolController::class, 'findRecipients'])->name('find-recipients');
            Route::get('administrators', [ToolController::class, 'administrators'])->name('administrators');
            Route::post('deliveries/{delivery}/retry', [DeliveryController::class, 'retry'])->whereNumber('delivery')->name('deliveries.retry');
        });
    });
