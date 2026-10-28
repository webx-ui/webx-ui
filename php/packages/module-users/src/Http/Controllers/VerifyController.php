<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use WebxUi\Users\Emails\Mask;
use WebxUi\Users\Emails\Verification;
use WebxUi\Users\Models\User;
use WebxUi\Users\Throttle;
use WebxUi\Users\Urls;
use WebxUi\Widgets\Forms\FormResponse;

/**
 * `GET /account/verify/{id}/{hash}` and `POST /account/verify/resend` (spec §8.1, §8.2).
 *
 * The link confirms the address without signing anybody in — it is opened wherever the mail is
 * read — and says so: «E-mail confirmed», with the way on for whoever is signed in here and the
 * way to sign in for a guest. A dead link says «The link is out of date» with the letter once
 * more for the visitor signed in, the sign-in for a guest. The letter once more is the signed-in
 * visitor's alone and takes no address: nobody sends letters to somebody else with it (§15).
 */
final readonly class VerifyController
{
    public function __construct(
        private Auth $auth,
        private Urls $urls,
    ) {}

    public function show(Request $request, Views $views, Verification $verification, Dispatcher $events): View
    {
        $user = $verification->visitor($request, (string) $request->route('id'), (string) $request->route('hash'));
        $current = $this->auth->guard(Urls::guard())->user();
        $current = $current instanceof User ? $current : null;

        if ($user === null) {
            return self::notice($views, $this->urls, $current, 'expired');
        }

        // The guard's own copy when it is the same visitor: the cookie of the page says «confirmed»
        // on this very response.
        if ($current !== null && $current->is($user)) {
            $user = $current;
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $events->dispatch(new Verified($user));
        }

        return self::notice($views, $this->urls, $current, 'confirmed');
    }

    public function resend(Request $request, Throttle $throttle, Verification $verification): FormResponse
    {
        $user = $this->auth->guard(Urls::guard())->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if ($user->hasVerifiedEmail()) {
            return FormResponse::message(trans('webx-users::auth.verify.already'));
        }

        $throttle->verify((int) $user->getKey());
        $verification->send($user);

        return FormResponse::message(trans('webx-users::auth.verify.sent', ['email' => Mask::email($user->email)]));
    }

    /**
     * A page of one sentence and the way on (spec §8.1): `confirmed`, `expired` — or `unverified`,
     * what a page behind `webx.visitor:verified` shows a visitor who has not confirmed (§8.4).
     */
    public static function notice(Views $views, Urls $urls, ?User $current, string $state): View
    {
        return $views->make('webx-users::notice', [
            'title' => trans('webx-users::auth.verify.'.$state.'.title'),
            'text' => trans('webx-users::auth.verify.'.$state.'.text', ['email' => $current === null ? '' : Mask::email($current->email)]),
            // Who is signed in and still unconfirmed may ask for the letter once more; a guest signs in.
            'resend' => $current !== null && ! $current->hasVerifiedEmail() ? $urls->to('verify.resend') : null,
            'link' => $current === null ? $urls->to('sign-in') : $urls->home(),
            'button' => trans($current === null ? 'webx-users::auth.verify.sign_in' : 'webx-users::auth.verify.continue'),
        ]);
    }
}
