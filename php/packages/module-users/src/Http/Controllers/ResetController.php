<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Mail\Factory as Mail;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Users\Mail\PasswordChanged;
use WebxUi\Users\Passwords\ResetLinks;
use WebxUi\Users\SignIn;
use WebxUi\Users\Throttle;
use WebxUi\Users\Urls;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;

/**
 * `GET|POST /account/reset/{token}` (spec §7 items 2–6): a new password and its repetition.
 *
 * A link works once and for `webx-users.reset_minutes`; a dead one shows «The link is out of
 * date» over the form that asks for a new one. After the change: the link is gone, the remember
 * token is new and every other session ends on its next request (TrackVisitor), the visitor is
 * signed in here and gets «Your password was changed».
 *
 * The reset proves the mailbox: an unconfirmed address is confirmed by it — and every social
 * network linked to the account until then is unlinked, or whoever registered somebody else's
 * address and linked their own network would keep a way in after the owner of the mailbox took
 * the account back (§7 item 5, §9.7).
 */
final readonly class ResetController
{
    public function __construct(
        private Urls $urls,
        private ResetLinks $links,
    ) {}

    public function show(Request $request, Views $views): View
    {
        $token = self::token($request);

        if ($this->links->find($token) === null) {
            return ForgotController::page($views, $this->urls, expired: true);
        }

        return $views->make('webx-users::reset', [
            'action' => $this->urls->to('reset.store', parameters: ['token' => $token]),
            'rules' => FormRules::for(self::browserRules(), self::attributes()),
            'min' => RegisterController::minimum(),
            // The invitation of the panel (§16.2) lands on the same page under its own words.
            'invited' => $this->links->purpose($token) === ResetLinks::INVITE,
        ]);
    }

    public function store(
        Request $request,
        Throttle $throttle,
        SignIn $signIn,
        Dispatcher $events,
        Mail $mail,
        ConnectionInterface $db,
    ): FormResponse {
        $throttle->reset((string) $request->ip());

        $user = $this->links->find(self::token($request));

        if ($user === null) {
            throw ValidationException::withMessages(['password' => trans('webx-users::auth.reset.expired')]);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'max:255', 'confirmed', RegisterController::password()],
        ], [], self::attributes());

        $db->transaction(function () use ($user, $data, $db): void {
            $unconfirmed = ! $user->hasVerifiedEmail();

            $user->password = (string) $data['password'];
            $user->setRememberToken(Str::random(60));

            if ($unconfirmed) {
                $user->email_verified_at = Date::now();
                $db->table('users_identities')->where('user_id', $user->getKey())->delete();
            }

            $user->save();
            $this->links->forget($user);
        });

        $signIn->in($request, $user);
        $events->dispatch(new VisitorSignedIn((int) $user->getKey(), 'reset'));

        $mail->mailer()->to($user->email)->send(new PasswordChanged($user, $this->urls->to('forgot')));

        return FormResponse::redirect($this->urls->home());
    }

    /** By name, not by place: the address of a language has its prefix among the parameters. */
    private static function token(Request $request): string
    {
        $token = $request->route('token');

        return is_string($token) ? $token : '';
    }

    /** @return array<string, list<string>> */
    private static function browserRules(): array
    {
        // The browser checks what it can read: a length and the repetition.
        return [
            'password' => ['required', 'string', 'min:'.RegisterController::minimum(), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    private static function attributes(): array
    {
        return [
            'password' => trans('webx-users::auth.reset.password'),
            'password_confirmation' => trans('webx-users::auth.reset.confirmation'),
        ];
    }
}
