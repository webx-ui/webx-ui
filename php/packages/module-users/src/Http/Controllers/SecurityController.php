<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Mail\Factory as Mail;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WebxUi\Users\Account\DeleteAccount;
use WebxUi\Users\Account\DeletionWarnings;
use WebxUi\Users\Emails\Mask;
use WebxUi\Users\Mail\PasswordChanged;
use WebxUi\Users\Mail\ResetPassword;
use WebxUi\Users\Models\User;
use WebxUi\Users\Passwords\ResetLinks;
use WebxUi\Users\SignIn;
use WebxUi\Users\Social\ProviderKeys;
use WebxUi\Users\Social\SocialAccounts;
use WebxUi\Users\Throttle;
use WebxUi\Users\Urls;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;

/**
 * `/account/security` — «Sign-in and security» (spec §11.1, §9.6, §14.1).
 *
 * - **The password**: the current one, a new one and its repetition; after it every other session
 *   ends and «Your password was changed» goes out. An account without a password gets «Set a
 *   password» instead — a reset link to its address, never a form on the spot: for an account of
 *   a social network with an unconfirmed address that would be a way to settle in somebody else's
 *   account (§9.6).
 * - **Sign out everywhere**: a new remember token — every other session and every remember
 *   cookie die with it (TrackVisitor), this device stays.
 * - **Delete the account**: the current password — or the account's own address where there is
 *   no password —, the warnings of the modules, then `DeleteAccount`.
 *
 * - **Social networks** (§9.6): every provider shown — linked (the address it gave, the date)
 *   with «Unlink», or «Link». The last way to sign in cannot be unlinked: the button is off with
 *   the reason, and the server refuses it all the same (`SocialAccounts::unlink`).
 */
final readonly class SecurityController
{
    public function __construct(
        private Auth $auth,
        private Urls $urls,
    ) {}

    public function show(Views $views, DeletionWarnings $warnings, ProviderKeys $keys, SocialAccounts $accounts): View
    {
        $user = ProfileController::user($this->auth);
        $min = RegisterController::minimum();

        return $views->make('webx-users::account.security', [
            'user' => $user,
            'hasPassword' => $user->password !== null,
            'min' => $min,
            'password' => [
                'action' => $this->urls->to('password.store'),
                'rules' => FormRules::for([
                    'current_password' => ['required', 'string'],
                    'password' => ['required', 'string', 'min:'.$min, 'confirmed'],
                    'password_confirmation' => ['required', 'string'],
                ], self::attributes()),
            ],
            'link' => $this->urls->to('password.link'),
            'sessions' => $this->urls->to('sessions.destroy'),
            'delete' => $this->urls->to('account.destroy'),
            'warnings' => $warnings->for($user->toVisitor()),
            'social' => $this->social($user, $keys, $accounts),
        ]);
    }

    /**
     * The rows of «Social networks»: one per provider shown.
     *
     * @return list<array{key: string, label: string, icon: string, linked: bool, email: ?string, since: ?Carbon, link: string, unlink: string, last: bool}>
     */
    private function social(User $user, ProviderKeys $keys, SocialAccounts $accounts): array
    {
        $providers = $keys->all();

        if ($providers === []) {
            return [];
        }

        $identities = $user->identities()->get()->keyBy('provider');
        $ways = $accounts->ways($user);
        $rows = [];

        foreach ($providers as $provider) {
            $identity = $identities->get($provider->key());

            $rows[] = [
                'key' => $provider->key(),
                'label' => (string) __($provider->label()),
                'icon' => $provider->icon(),
                'linked' => $identity !== null,
                'email' => $identity?->email,
                'since' => $identity?->created_at,
                'link' => $this->urls->to('social', parameters: ['provider' => $provider->key(), 'link' => 1]),
                'unlink' => $this->urls->named('webx-users.social.unlink', ['provider' => $provider->key()]),
                // The same rule as `SocialAccounts::canUnlink`, from the ways read once for the page.
                'last' => $identity !== null && array_diff($ways, [$provider->key()]) === [],
            ];
        }

        return $rows;
    }

    public function password(Request $request, Hasher $hasher, Throttle $throttle, SignIn $signIn, Mail $mail): FormResponse
    {
        $user = ProfileController::user($this->auth);

        if ($user->password === null) {
            throw ValidationException::withMessages(['current_password' => trans('webx-users::account.password.none')]);
        }

        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', RegisterController::password()],
        ], [], self::attributes());

        $this->checkPassword($user, (string) $data['current_password'], 'current_password', $hasher, $throttle);

        $user->password = (string) $data['password'];
        $user->setRememberToken(Str::random(60));
        $user->save();
        $signIn->keep($request, $user);

        $mail->mailer()->to($user->email)->send(new PasswordChanged($user, $this->urls->to('forgot')));

        return FormResponse::message(trans('webx-users::account.password.changed'));
    }

    /** «Set a password»: the reset link of §7 to the account's address (§9.6). */
    public function link(Throttle $throttle, ResetLinks $links, Mail $mail): FormResponse
    {
        $user = ProfileController::user($this->auth);

        $throttle->passwordLink((int) $user->getKey());

        $link = $this->urls->to('reset', parameters: ['token' => $links->issue($user)]);
        $mail->mailer()->to($user->email)->send(new ResetPassword($user, $link, ResetLinks::minutes()));

        return FormResponse::message(trans('webx-users::account.password.link_sent', ['email' => Mask::email($user->email)]));
    }

    public function signOutEverywhere(Request $request, SignIn $signIn): FormResponse
    {
        $user = ProfileController::user($this->auth);

        $user->setRememberToken(Str::random(60));
        $user->save();
        $signIn->keep($request, $user);

        return FormResponse::message(trans('webx-users::account.sessions.done'));
    }

    public function destroy(Request $request, Hasher $hasher, Throttle $throttle, DeleteAccount $delete, SignIn $signIn): FormResponse
    {
        $user = ProfileController::user($this->auth);

        if ($user->password !== null) {
            $data = $request->validate(['password' => ['required', 'string', 'max:255']], [], [
                'password' => trans('webx-users::account.delete.password'),
            ]);

            $this->checkPassword($user, (string) $data['password'], 'password', $hasher, $throttle);
        } else {
            $data = $request->validate(['email' => ['required', 'string', 'max:191']], [], [
                'email' => trans('webx-users::account.delete.email'),
            ]);

            if (User::normaliseEmail((string) $data['email']) !== $user->email) {
                throw ValidationException::withMessages(['email' => trans('webx-users::account.delete.wrong_email')]);
            }
        }

        $delete($user);

        // The guard forgets the visitor without a word to the database: the row is gone.
        $signIn->out($request);

        return FormResponse::redirect($this->urls->home());
    }

    private function checkPassword(User $user, string $password, string $field, Hasher $hasher, Throttle $throttle): void
    {
        $id = (int) $user->getKey();

        $throttle->password($id, $field);

        if (! $hasher->check($password, (string) $user->password)) {
            $throttle->passwordFailed($id);

            throw ValidationException::withMessages([$field => trans('webx-users::auth.email.wrong_password')]);
        }
    }

    /** @return array<string, string> */
    private static function attributes(): array
    {
        return [
            'current_password' => trans('webx-users::account.password.current'),
            'password' => trans('webx-users::account.password.new'),
            'password_confirmation' => trans('webx-users::account.password.confirmation'),
        ];
    }
}
