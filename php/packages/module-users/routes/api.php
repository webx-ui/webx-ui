<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Users\Http\Controllers\Panel\GroupController;
use WebxUi\Users\Http\Controllers\Panel\SettingsController;
use WebxUi\Users\Http\Controllers\Panel\UserController;
use WebxUi\Users\Panel\UsersModule;

/*
 * «Customers» in the panel (spec §16.1): `users.view` reads the list, the card and the groups —
 * with the addresses and the phones, which is personal data; `users.manage` writes; the settings
 * are `users.settings` alone. Whoever may manage may also read. No route answers with a password,
 * a token or a secret.
 */
Route::prefix((string) config('webx-admin.api_path').'/users')
    ->middleware('webx.panel')
    ->name('webx.users.panel.')
    ->group(function (): void {
        $read = 'cms.can:'.UsersModule::VIEW.','.UsersModule::MANAGE;
        $write = 'cms.can:'.UsersModule::MANAGE;

        Route::middleware($read)->group(function (): void {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('meta', [UserController::class, 'meta'])->name('meta');
            Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
            Route::get('{user}', [UserController::class, 'show'])->whereNumber('user')->name('show');
        });

        Route::middleware($write)->group(function (): void {
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::put('{user}', [UserController::class, 'update'])->whereNumber('user')->name('update');
            Route::get('{user}/warnings', [UserController::class, 'warnings'])->whereNumber('user')->name('warnings');
            Route::delete('{user}', [UserController::class, 'destroy'])->whereNumber('user')->name('destroy');
            Route::post('{user}/block', [UserController::class, 'block'])->whereNumber('user')->name('block');
            Route::post('{user}/unblock', [UserController::class, 'unblock'])->whereNumber('user')->name('unblock');
            Route::post('{user}/verify', [UserController::class, 'verify'])->whereNumber('user')->name('verify');
            Route::post('{user}/reset-link', [UserController::class, 'sendReset'])->whereNumber('user')->name('reset-link');
            Route::post('{user}/verification', [UserController::class, 'sendVerification'])->whereNumber('user')->name('verification');
            Route::delete('{user}/ways/{provider}', [UserController::class, 'unlink'])->whereNumber('user')->where('provider', '[a-z0-9-]+')->name('unlink');

            Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
            Route::put('groups/{group}', [GroupController::class, 'update'])->whereNumber('group')->name('groups.update');
            Route::delete('groups/{group}', [GroupController::class, 'destroy'])->whereNumber('group')->name('groups.destroy');
        });

        Route::middleware('cms.can:'.UsersModule::SETTINGS)->group(function (): void {
            Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::put('settings/providers/{key}', [SettingsController::class, 'provider'])->where('key', '[a-z0-9-]+')->name('settings.provider');
        });
    });
