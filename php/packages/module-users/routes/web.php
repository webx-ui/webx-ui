<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Users\Http\Controllers\EmailController;
use WebxUi\Users\Http\Controllers\ForgotController;
use WebxUi\Users\Http\Controllers\RegisterController;
use WebxUi\Users\Http\Controllers\ResetController;
use WebxUi\Users\Http\Controllers\SignInController;
use WebxUi\Users\Http\Controllers\SignOutController;
use WebxUi\Users\Http\Controllers\StateController;
use WebxUi\Users\Http\Controllers\VerifyController;
use WebxUi\Users\Http\KeepPrivate;
use WebxUi\Users\Http\ScriptController;

/*
 * The visitor's pages (spec §6.1), under `webx-users.prefix`, each twice where the site puts the
 * language in the path: as it is, and behind `{webxLocale}` — which OneSpellingPerAddress holds to
 * the site's languages and to one spelling of the default one, as the blog's feed.
 *
 * `webx_service`: no page of these is a place of the site a link field offers or an SEO rule
 * edits (`WebxUi\Admin\Links\SiteRoutes::SERVICE`); `KeepPrivate` — none is cached anywhere.
 * The writes are limited inside the controllers, which answer a form in words (§15).
 */
$prefix = trim((string) config('webx-users.prefix', 'account'), '/');
$middleware = [...array_values((array) config('webx-users.middleware', ['web', 'webx.locale'])), KeepPrivate::class];
$localised = (string) config('webx-localization.strategy', 'prefix') === 'prefix';

$pages = static function (string $suffix): void {
    Route::get('sign-in', [SignInController::class, 'show'])->name('sign-in'.$suffix);
    Route::post('sign-in', [SignInController::class, 'store'])->name('sign-in.store'.$suffix);
    Route::get('register', [RegisterController::class, 'show'])->name('register'.$suffix);
    Route::post('register', [RegisterController::class, 'store'])->name('register.store'.$suffix);
    Route::post('sign-out', SignOutController::class)->name('sign-out'.$suffix);
    Route::get('forgot', [ForgotController::class, 'show'])->name('forgot'.$suffix);
    Route::post('forgot', [ForgotController::class, 'store'])->name('forgot.store'.$suffix);
    Route::get('reset/{token}', [ResetController::class, 'show'])->name('reset'.$suffix);
    Route::post('reset/{token}', [ResetController::class, 'store'])->name('reset.store'.$suffix);
    Route::get('verify/{id}/{hash}', [VerifyController::class, 'show'])->name('verify'.$suffix);
    Route::post('verify/resend', [VerifyController::class, 'resend'])->middleware('webx.visitor')->name('verify.resend'.$suffix);
    Route::post('email', [EmailController::class, 'store'])->middleware('webx.visitor')->name('email.store'.$suffix);
    Route::get('email/{id}', [EmailController::class, 'confirm'])->name('email.confirm'.$suffix);
    Route::get('state', StateController::class)->name('state'.$suffix);
};

Route::group(['webx_service' => true], static function () use ($prefix, $middleware, $localised, $pages): void {
    Route::middleware($middleware)->prefix($prefix)->name('webx-users.')->group(static fn () => $pages(''));

    if ($localised) {
        Route::middleware([...$middleware, OneSpellingPerAddress::class])
            ->prefix('{'.OneSpellingPerAddress::PARAMETER.'}/'.$prefix)
            ->name('webx-users.')
            ->group(static fn () => $pages('.localised'));
    }
});

// The script of the pages cached for everybody (§11.6): no session, no language, cached for good.
Route::get('webx/users/users.js', ScriptController::class)->name('webx-users.script');
