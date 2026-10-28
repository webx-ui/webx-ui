<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Users\Account\AccountRoutes;
use WebxUi\Users\Http\Controllers\DevProviderController;
use WebxUi\Users\Http\Controllers\EmailController;
use WebxUi\Users\Http\Controllers\ForgotController;
use WebxUi\Users\Http\Controllers\ProfileController;
use WebxUi\Users\Http\Controllers\RegisterController;
use WebxUi\Users\Http\Controllers\ResetController;
use WebxUi\Users\Http\Controllers\SecurityController;
use WebxUi\Users\Http\Controllers\SignInController;
use WebxUi\Users\Http\Controllers\SignOutController;
use WebxUi\Users\Http\Controllers\SocialController;
use WebxUi\Users\Http\Controllers\StateController;
use WebxUi\Users\Http\Controllers\VerifyController;
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
$prefix = AccountRoutes::prefix();
$middleware = AccountRoutes::middleware();
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
    // «Almost done» before the trip to a provider: `complete` is no provider's key.
    Route::get('auth/complete', [SocialController::class, 'complete'])->name('complete'.$suffix);
    Route::post('auth/complete', [SocialController::class, 'completeStore'])->name('complete.store'.$suffix);
    Route::get('auth/{provider}', [SocialController::class, 'redirect'])->where('provider', '[a-z0-9-]+')->name('social'.$suffix);
};

Route::group(['webx_service' => true], static function () use ($prefix, $middleware, $localised, $pages): void {
    Route::middleware($middleware)->prefix($prefix)->name('webx-users.')->group(static fn () => $pages(''));

    if ($localised) {
        Route::middleware([...$middleware, OneSpellingPerAddress::class])
            ->prefix('{'.OneSpellingPerAddress::PARAMETER.'}/'.$prefix)
            ->name('webx-users.')
            ->group(static fn () => $pages('.localised'));
    }

    // The return of a provider (§9.4): one address for every language — it is written into the
    // provider's console —, GET or POST, outside CSRF (UsersServiceProvider): `state` guards it.
    Route::middleware($middleware)->prefix($prefix)->group(static function (): void {
        Route::match(['GET', 'POST'], 'auth/{provider}/callback', [SocialController::class, 'callback'])
            ->where('provider', '[a-z0-9-]+')
            ->name('webx-users.social.callback');

        // The «provider» of the test provider (Social\DevProvider): registered on a local machine only.
        if (app()->environment('local')) {
            Route::get('auth/dev/pretend', DevProviderController::class)->name('webx-users.social.dev');
        }
    });
});

/*
 * The account (spec §11.1): the profile and «Sign-in and security», declared the way a module
 * declares its section — behind `webx.visitor`, with the copy behind the language prefix.
 */
AccountRoutes::group(static function (): void {
    Route::get('', [ProfileController::class, 'index'])->name('webx-users.account');
    Route::get('profile', [ProfileController::class, 'show'])->name('webx-users.profile');
    Route::post('profile', [ProfileController::class, 'store'])->name('webx-users.profile.store');
    Route::get('security', [SecurityController::class, 'show'])->name('webx-users.security');
    Route::post('password', [SecurityController::class, 'password'])->name('webx-users.password.store');
    Route::post('password/link', [SecurityController::class, 'link'])->name('webx-users.password.link');
    Route::post('sign-out-everywhere', [SecurityController::class, 'signOutEverywhere'])->name('webx-users.sessions.destroy');
    Route::post('delete', [SecurityController::class, 'destroy'])->name('webx-users.account.destroy');
    Route::post('social/{provider}/unlink', [SocialController::class, 'unlink'])->where('provider', '[a-z0-9-]+')->name('webx-users.social.unlink');
});

// The scripts of the pages cached for everybody (§11.6) and of the dialog of the sign-in (§12.1):
// no session, no language, cached for good.
Route::get('webx/users/users.js', ScriptController::class)->name('webx-users.script');
Route::get('webx/users/sign-in.js', [ScriptController::class, 'signIn'])->name('webx-users.script.sign-in');
