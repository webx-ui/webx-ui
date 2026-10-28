<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Users\Events\VisitorSignInFailed;
use WebxUi\Users\Models\User;
use WebxUi\Users\SignIn;
use WebxUi\Users\Social\SocialAccounts;
use WebxUi\Users\Throttle;
use WebxUi\Users\Urls;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;

/**
 * `GET|POST /account/sign-in` (spec §6.2, §15).
 *
 * Every refusal of a pair is one sentence — no such address, an account without a password, a
 * wrong password — and takes as long: an address that is not there is checked against a stand-in
 * hash all the same. A blocked account is told so only on the right password: guessing does not
 * find out who is blocked.
 */
final readonly class SignInController
{
    public const array RULES = [
        'email' => ['required', 'string', 'email', 'max:191'],
        'password' => ['required', 'string', 'max:255'],
        'remember' => ['nullable', 'boolean'],
    ];

    public function __construct(
        private Auth $auth,
        private Urls $urls,
        private Config $config,
    ) {}

    public function show(Request $request, Views $views): View|RedirectResponse
    {
        $back = $this->urls->local($request->query(Urls::BACK));

        if ($this->auth->guard(Urls::guard())->check()) {
            return new RedirectResponse($back ?? $this->urls->home());
        }

        return $views->make('webx-users::sign-in', [
            'action' => $this->urls->to('sign-in'),
            'rules' => FormRules::for(self::RULES, self::attributes()),
            'back' => $back,
            'remember' => (bool) $this->config->get('webx-users.remember', true),
            'forgot' => $this->urls->to('forgot'),
            'register' => SignIn::registrationOpen() ? $this->urls->to('register', $back) : null,
        ]);
    }

    public function store(Request $request, Throttle $throttle, Hasher $hasher, Dispatcher $events, SignIn $signIn, SocialAccounts $social): FormResponse
    {
        $data = $request->validate(self::RULES, [], self::attributes());
        $email = User::normaliseEmail((string) $data['email']);
        $ip = (string) $request->ip();

        $throttle->signIn($email, $ip);

        $user = User::query()->byEmail($email)->first();
        $hash = $user->password ?? self::standIn($hasher);
        $right = $hasher->check((string) $data['password'], $hash) && $user?->password !== null;

        if (! $right || $user === null) {
            $throttle->signInFailed($email, $ip);
            $events->dispatch(new VisitorSignInFailed(hash('sha256', $email), $request->ip()));

            throw ValidationException::withMessages(['email' => trans('webx-users::auth.failed')]);
        }

        if ($user->isBlocked()) {
            throw ValidationException::withMessages(['email' => trans('webx-users::auth.blocked')]);
        }

        $throttle->signedIn($email, $ip);
        $remember = (bool) ($data['remember'] ?? false) && (bool) $this->config->get('webx-users.remember', true);
        $signIn->in($request, $user, $remember);
        $events->dispatch(new VisitorSignedIn((int) $user->getKey(), 'password'));
        // A social network waiting for this account's password is linked now (§9.7).
        $social->linkWaiting($request, $user);

        return FormResponse::redirect($this->urls->local($request->input(Urls::BACK)) ?? $this->urls->home());
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'email' => trans('webx-users::auth.fields.email'),
            'password' => trans('webx-users::auth.fields.password'),
        ];
    }

    /** A hash of nothing in particular, made once a process in the site's own algorithm. */
    private static function standIn(Hasher $hasher): string
    {
        static $hash = null;

        return $hash ??= $hasher->make(bin2hex(random_bytes(16)));
    }
}
