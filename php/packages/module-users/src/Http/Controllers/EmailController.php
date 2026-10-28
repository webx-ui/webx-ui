<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Mail\Factory as Mail;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use WebxUi\Users\Emails\EmailChange;
use WebxUi\Users\Emails\Mask;
use WebxUi\Users\Mail\EmailChanged;
use WebxUi\Users\Models\User;
use WebxUi\Users\Passwords\ResetLinks;
use WebxUi\Users\Throttle;
use WebxUi\Users\Urls;
use WebxUi\Widgets\Forms\FormResponse;

/**
 * `POST /account/email` and `GET /account/email/{id}` (spec §8.5, decision 16).
 *
 * The request takes the new address and the current password — an account without a password
 * has none to give, and the mailbox alone confirms it — and sends the link to the new address;
 * the account does not change. The link moves the sign-in there and confirms it: whoever opened
 * it reads that mailbox. The reset link of the old address dies, and the old address hears
 * «Your sign-in address was changed». The form is the profile's (the account, §11).
 */
final readonly class EmailController
{
    public function __construct(
        private Auth $auth,
        private Urls $urls,
    ) {}

    public function store(Request $request, Hasher $hasher, Throttle $throttle, EmailChange $change): FormResponse
    {
        $user = $this->auth->guard(Urls::guard())->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => [$user->password === null ? 'nullable' : 'required', 'string', 'max:255'],
        ], [], [
            'email' => trans('webx-users::auth.email.new'),
            'password' => trans('webx-users::auth.email.password'),
        ]);

        if ($user->password !== null && ! $hasher->check((string) $data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => trans('webx-users::auth.email.wrong_password')]);
        }

        $email = User::normaliseEmail((string) $data['email']);

        if ($email === $user->email) {
            throw ValidationException::withMessages(['email' => trans('webx-users::auth.email.same')]);
        }

        if (User::query()->byEmail($email)->exists()) {
            throw ValidationException::withMessages(['email' => trans('webx-users::auth.email.taken')]);
        }

        $throttle->emailChange((int) $user->getKey());
        $change->send($user, $email);
        $request->session()->put(EmailChange::PENDING, $email);

        return FormResponse::message(trans('webx-users::auth.email.sent', ['email' => Mask::email($email)]));
    }

    public function confirm(Request $request, Views $views, EmailChange $change, ResetLinks $links, Mail $mail, ConnectionInterface $db): View
    {
        $current = $this->auth->guard(Urls::guard())->user();
        $current = $current instanceof User ? $current : null;
        $resolved = $change->resolve($request, (string) $request->route('id'));

        if ($resolved === null) {
            return VerifyController::notice($views, $this->urls, $current, 'expired');
        }

        [$user, $email] = $resolved;

        if ($current !== null && $current->is($user)) {
            $user = $current;
        }

        $old = $user->email;

        $db->transaction(function () use ($user, $email, $links): void {
            $user->email = $email;
            $user->email_verified_at = Date::now();
            $user->save();
            $links->forget($user);
        });

        if ($request->hasSession()) {
            $request->session()->forget(EmailChange::PENDING);
        }

        $mail->mailer()->to($old)->send(new EmailChanged($user, Mask::email($email), $this->urls->to('forgot')));

        return $views->make('webx-users::notice', [
            'title' => trans('webx-users::auth.email.changed.title'),
            'text' => trans('webx-users::auth.email.changed.text', ['email' => $email]),
            'resend' => null,
            'link' => $current === null ? $this->urls->to('sign-in') : $this->urls->home(),
            'button' => trans($current === null ? 'webx-users::auth.verify.sign_in' : 'webx-users::auth.verify.continue'),
        ]);
    }
}
