<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Auth\Http\Controllers\AdminController;
use WebxUi\Auth\Http\Controllers\ConnectionController;
use WebxUi\Auth\Http\Controllers\CsrfCookieController;
use WebxUi\Auth\Http\Controllers\KeyController;
use WebxUi\Auth\Http\Controllers\McpCallController;
use WebxUi\Auth\Http\Controllers\MeController;
use WebxUi\Auth\Http\Controllers\PanelLocaleController;
use WebxUi\Auth\Http\Controllers\PanelSiteBarController;
use WebxUi\Auth\Http\Controllers\PanelThemeController;
use WebxUi\Auth\Http\Controllers\ProfileController;
use WebxUi\Auth\Http\Controllers\RoleController;
use WebxUi\Auth\Http\Controllers\SessionController;

Route::prefix((string) config('webx-admin.api_path').'/auth')
    // The language before the session: a sign-in that fails has to say so in a language the
    // person reading it understands, and at that point there is nobody to ask but the browser.
    ->middleware(['web', 'webx.panel-locale'])
    ->name('webx.auth.')
    ->group(function (): void {
        // The cookie a browser needs before its first write, and the only thing here that is
        // public on purpose rather than by necessity.
        Route::get('csrf-cookie', CsrfCookieController::class)->name('csrf-cookie');

        // Public by necessity, throttled because of it.
        Route::post('login', [SessionController::class, 'store'])
            ->middleware('throttle:'.(string) config('webx-auth.throttle'))
            ->name('login');

        Route::middleware(['cms.auth', 'webx.history'])->group(function (): void {
            Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
            Route::get('me', MeController::class)->name('me');
            // Yourself, and only the parts of yourself that are yours to change. Deliberately
            // not behind 'admins.manage': that permission is about other people.
            Route::put('me', ProfileController::class)->name('me.update');
            Route::put('locale', PanelLocaleController::class)->name('locale');
            Route::put('theme', PanelThemeController::class)->name('theme');
            Route::put('site-bar', PanelSiteBarController::class)->name('site-bar');

            // The agents this person let in, like the profile above: your own connections are
            // yours to see and to end, and somebody else's needs `admins.manage` — which the
            // controller asks for itself, because one endpoint answers both questions.
            Route::get('connections', [ConnectionController::class, 'index'])->name('connections.index');
            Route::delete('connections/{connection}', [ConnectionController::class, 'destroy'])
                ->name('connections.destroy');

            // Keys for programs, the same way: your own need nothing, somebody else's are
            // speaking for them — `admins.manage` and the rules, asked by the controller.
            Route::get('keys', [KeyController::class, 'index'])->name('keys.index');
            Route::get('keys/scopes', [KeyController::class, 'scopes'])->name('keys.scopes');
            Route::post('keys', [KeyController::class, 'store'])->name('keys.store');
            Route::delete('keys/{key}', [KeyController::class, 'destroy'])->name('keys.destroy');

            // Reading the list is not managing it: a module that wants to show who wrote
            // something, or offer a picker of people to assign work to, needs the first and
            // has no business with the second.
            Route::middleware('cms.can:admins.view,admins.manage')->group(function (): void {
                Route::get('admins', [AdminController::class, 'index'])->name('admins.index');
                Route::get('admins/{admin}', [AdminController::class, 'show'])->name('admins.show');
            });

            // The roles: read by whoever deals with people or roles — the pickers that assign them,
            // the screen that edits them — and written only with `roles.manage`, under the rules
            // the controller asks (WEBX_UI_PERMISSIONS.md §6.2).
            Route::middleware('cms.can:admins.view,admins.manage,roles.manage,roles.assign')->group(function (): void {
                Route::get('roles', [RoleController::class, 'index'])->name('roles');
                Route::get('roles/permissions', [RoleController::class, 'permissions'])->name('roles.permissions');
                Route::get('roles/{role}', [RoleController::class, 'show'])->whereNumber('role')->name('roles.show');
            });

            Route::middleware('cms.can:roles.manage')->group(function (): void {
                Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
                Route::put('roles/{role}', [RoleController::class, 'update'])->whereNumber('role')->name('roles.update');
                Route::delete('roles/{role}', [RoleController::class, 'destroy'])->whereNumber('role')->name('roles.destroy');
            });

            Route::middleware('cms.can:admins.manage')->group(function (): void {
                Route::post('admins', [AdminController::class, 'store'])->name('admins.store');
                Route::patch('admins/{admin}', [AdminController::class, 'update'])->name('admins.update');
                Route::delete('admins/{admin}', [AdminController::class, 'destroy'])->name('admins.destroy');
            });

            // What agents did, behind the permission the sign-in trail is behind: the same
            // question, asked about a program rather than a hand.
            Route::middleware('cms.can:admins.audit')->group(function (): void {
                Route::get('mcp-calls', McpCallController::class)->name('mcp-calls');
            });
        });
    });
