<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Auth\Http\Controllers\ConsentController;

// Beside Passport's own `oauth/authorize`, under the same prefix: the consent screen posts
// here instead of to Passport, so that the answer is written down on its way through.
Route::prefix(trim((string) config('webx-mcp.oauth.prefix', 'oauth'), '/'))
    ->middleware('web')
    ->name('webx.auth.consent.')
    ->group(function (): void {
        Route::post('consent', [ConsentController::class, 'approve'])
            ->middleware('auth:'.(string) config('webx-auth.guard'))
            ->name('approve');

        // No guard: a guest pressing it signs nobody out and is sent to sign in, which is
        // where they were going anyway.
        Route::post('consent/switch', [ConsentController::class, 'switch'])->name('switch');
    });
