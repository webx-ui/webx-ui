<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use WebxUi\Inbox\Http\Controllers\ScriptController;
use WebxUi\Inbox\Http\Controllers\SubmitController;

$prefix = trim((string) config('webx-inbox.path', 'webx/forms'), '/');

// Outside every group: a script needs no session, and a cookie on it would only stop a proxy
// from sharing what is meant to be shared. Declared before the intake so that `inbox.js` is
// not read as the slug of a form (the intake is a POST and this a GET, but a route that
// depends on that is a route waiting for somebody to add a verb).
Route::get("{$prefix}/inbox.js", ScriptController::class)->name('webx.inbox.script');

/**
 * The door a form on the site posts to (§6).
 *
 * The stack is written out rather than taken from the `web` group, because it is the `web`
 * group minus one thing: `VerifyCsrfToken`. A page cached whole carries the token that was
 * minted when the cache was written, and a stale token is a 419 that happens only on a live
 * site and only after a while — which is the worst possible shape for a bug in the one part of
 * a site that customers use (§2.8).
 *
 * Everything else stays, the session included: a form without JavaScript reports its errors
 * through `back()->withErrors()`, and that needs somewhere to put them.
 */
Route::post("{$prefix}/{slug}", SubmitController::class)
    ->middleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        SubstituteBindings::class,
        ThrottleRequests::class.':webx-inbox',
    ])
    ->name('webx.inbox.submit');
