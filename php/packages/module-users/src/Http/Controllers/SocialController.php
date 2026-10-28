<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Visitors\Events\VisitorRegistered;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Localization\Locales;
use WebxUi\Users\Fields\FieldValues;
use WebxUi\Users\Fields\Placement;
use WebxUi\Users\Fields\Requirement;
use WebxUi\Users\Models\User;
use WebxUi\Users\SignIn;
use WebxUi\Users\Social\Provider;
use WebxUi\Users\Social\ProviderFailed;
use WebxUi\Users\Social\ProviderKeys;
use WebxUi\Users\Social\SocialAccounts;
use WebxUi\Users\Social\SocialProfile;
use WebxUi\Users\Social\SocialSession;
use WebxUi\Users\Throttle;
use WebxUi\Users\Urls;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;

/**
 * Signing in through a social network (spec §9.4–§9.7).
 *
 * - `GET /account/auth/{provider}` — off to the provider with the module's `state`; `?link=1`
 *   from the account links instead of signing in (only a visitor signed in).
 * - `GET|POST /account/auth/{provider}/callback` — one address for every language, outside CSRF
 *   (the `state` guards it). The person is found by the provider's id; else an account with the
 *   address is linked only when the provider vouches for it (§9.7), and asked for the password
 *   otherwise; else a new account — or «Almost done» first, when the address or a field the site
 *   requires is missing (§9.5).
 * - `GET|POST /account/auth/complete` — «Almost done».
 * - `POST /account/social/{provider}/unlink` — in the account, never the last way to sign in.
 *
 * A provider that is not shown (§9.3) has none of these: 404.
 */
final readonly class SocialController
{
    public function __construct(
        private Auth $auth,
        private Urls $urls,
        private ProviderKeys $keys,
        private SocialAccounts $accounts,
    ) {}

    public function redirect(Request $request, Throttle $throttle, Locales $locales): RedirectResponse
    {
        $found = $this->keys->find(self::provider($request)) ?? abort(404);
        $throttle->social((string) $request->ip());

        $link = $request->boolean('link');
        $signedIn = $this->auth->guard(Urls::guard())->user() instanceof User;
        $back = $this->urls->local($request->query(Urls::BACK));

        if ($link && ! $signedIn) {
            return new RedirectResponse($this->urls->to('sign-in', $this->urls->to('security')));
        }

        if (! $link && $signedIn) {
            return new RedirectResponse($back ?? $this->urls->home());
        }

        $state = (new SocialSession($request->session()))->start($found->key(), $link ? SocialSession::LINK : SocialSession::SIGN_IN, $back, $locales->current());

        return $found->redirect($this->keys->config($found), $state);
    }

    public function callback(Request $request, Locales $locales, SignIn $signIn, Dispatcher $events): RedirectResponse
    {
        $found = $this->keys->find(self::provider($request)) ?? abort(404);
        $session = new SocialSession($request->session());
        $trip = $session->finish($found->key(), $request->input('state'));

        if ($trip === null) {
            return $this->failed($session, $this->urls->to('sign-in'));
        }

        // The pages that follow speak the language the visitor left in.
        if ($trip['locale'] !== '') {
            $locales->use($trip['locale']);
        }

        $link = $trip['intent'] === SocialSession::LINK;

        try {
            $profile = $found->profile($this->keys->config($found), $request);
        } catch (ProviderFailed $failed) {
            Log::info('webx-users: '.$found->key().' sign-in failed: '.$failed->getMessage());

            return $this->failed($session, $this->urls->to($link ? 'security' : 'sign-in', $link ? null : $trip['back']));
        }

        return $link
            ? $this->link($session, $found, $profile)
            : $this->signIn($request, $session, $found, $profile, $trip['back'], $signIn, $events);
    }

    public function complete(Request $request, Views $views, FieldValues $fields): View|RedirectResponse
    {
        abort_unless(SignIn::registrationOpen(), 404);

        [$pending, $provider] = $this->pending($request);

        if ($pending === null || $provider === null) {
            return new RedirectResponse($this->urls->to('sign-in'));
        }

        $asked = $this->asked($pending['profile']);

        return $views->make('webx-users::complete', [
            'action' => $this->urls->to('complete.store'),
            'rules' => FormRules::for(
                [...$asked, ...$fields->formRules(Placement::Registration)],
                [...RegisterController::attributes(), ...$fields->attributes(Placement::Registration)],
            ),
            'provider' => __($provider->label()),
            'asked' => array_keys($asked),
            'fields' => array_values(array_filter($fields->controls(Placement::Registration), static fn (array $control): bool => $control['required'])),
        ]);
    }

    public function completeStore(Request $request, Throttle $throttle, FieldValues $fields, SignIn $signIn, Dispatcher $events): FormResponse
    {
        abort_unless(SignIn::registrationOpen(), 404);

        $session = new SocialSession($request->session());
        [$pending, $provider] = $this->pending($request);

        if ($pending === null || $provider === null) {
            throw ValidationException::withMessages(['email' => trans('webx-users::auth.social.expired')]);
        }

        $throttle->register((string) $request->ip());

        $errors = [];
        $data = [];
        $extra = [];

        try {
            $data = $request->validate($this->asked($pending['profile']), [], RegisterController::attributes());
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        try {
            $input = $request->input(FieldValues::INPUT);
            $extra = $fields->validate(is_array($input) ? $input : [], Placement::Registration);
        } catch (ValidationException $exception) {
            $errors = [...$errors, ...$exception->errors()];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $profile = $pending['profile'];
        $email = User::normaliseEmail($profile->email ?? (string) $data['email']);
        $account = User::query()->byEmail($email)->first();

        // An address typed here is no more vouched for than Facebook's: its account is linked by
        // its password (§9.5, §9.7).
        if ($account !== null) {
            $session->wait($provider->key(), $profile, (int) $account->getKey(), $pending['back']);
            $session->notice(trans('webx-users::auth.social.sign_in_to_link', ['provider' => __($provider->label())]));

            return FormResponse::redirect($this->urls->to('sign-in', $pending['back']));
        }

        $this->register($request, $provider, $profile, $email, $data, $extra, $signIn, $events);
        $session->forget();

        return FormResponse::redirect($pending['back'] ?? $this->urls->home());
    }

    public function unlink(Request $request): FormResponse
    {
        $user = ProfileController::user($this->auth);

        $this->accounts->unlink($user, self::provider($request));

        return FormResponse::redirect($this->urls->to('security'));
    }

    private function signIn(Request $request, SocialSession $session, Provider $provider, SocialProfile $profile, ?string $back, SignIn $signIn, Dispatcher $events): RedirectResponse
    {
        $identity = $this->accounts->identity($provider, $profile);
        $user = $identity?->user;

        if ($user === null && $profile->email !== null) {
            $account = User::query()->byEmail($profile->email)->first();

            if ($account !== null && ! $this->accounts->vouchedFor($account, $provider, $profile)) {
                $session->wait($provider->key(), $profile, (int) $account->getKey(), $back);
                $session->notice(trans('webx-users::auth.social.sign_in_to_link', ['provider' => __($provider->label())]));

                return new RedirectResponse($this->urls->to('sign-in', $back));
            }

            if ($account !== null && ! $account->isBlocked()) {
                $identity = $this->accounts->link($account, $provider, $profile);
            }

            $user = $account;
        }

        if ($user !== null) {
            if ($user->isBlocked()) {
                $session->notice(trans('webx-users::auth.blocked'));

                return new RedirectResponse($this->urls->to('sign-in', $back));
            }

            $identity?->forceFill(['last_used_at' => Date::now()])->save();
            $signIn->in($request, $user);
            $events->dispatch(new VisitorSignedIn((int) $user->getKey(), $provider->key()));

            return new RedirectResponse($back ?? $this->urls->home());
        }

        if (! SignIn::registrationOpen()) {
            $session->notice(trans('webx-users::auth.social.closed', ['provider' => __($provider->label())]));

            return new RedirectResponse($this->urls->to('sign-in', $back));
        }

        if ($this->asked($profile) !== [] || $this->requiredFields() !== []) {
            $session->wait($provider->key(), $profile, null, $back);

            return new RedirectResponse($this->urls->to('complete'));
        }

        $this->register($request, $provider, $profile, User::normaliseEmail((string) $profile->email), [], [], $signIn, $events);

        return new RedirectResponse($back ?? $this->urls->home());
    }

    private function link(SocialSession $session, Provider $provider, SocialProfile $profile): RedirectResponse
    {
        $security = $this->urls->to('security');
        $user = $this->auth->guard(Urls::guard())->user();

        if (! $user instanceof User) {
            return new RedirectResponse($this->urls->to('sign-in', $security));
        }

        $identity = $this->accounts->identity($provider, $profile);
        $label = __($provider->label());

        if ($identity !== null) {
            $session->notice(trans($identity->user_id === (int) $user->getKey() ? 'webx-users::account.social.linked' : 'webx-users::account.social.elsewhere', ['provider' => $label]));

            return new RedirectResponse($security);
        }

        if ($user->identities()->where('provider', $provider->key())->exists()) {
            $session->notice(trans('webx-users::account.social.other', ['provider' => $label]));

            return new RedirectResponse($security);
        }

        $this->accounts->link($user, $provider, $profile);
        $session->notice(trans('webx-users::account.social.linked', ['provider' => $label]));

        return new RedirectResponse($security);
    }

    /**
     * A new account of the provider's person (§9.5): no password; the address confirmed only when
     * the provider vouches for the one it gave, a letter of confirmation otherwise.
     *
     * @param  array<string, mixed>  $data  what «Almost done» asked
     * @param  array<string, mixed>  $extra  the fields of the site
     */
    private function register(Request $request, Provider $provider, SocialProfile $profile, string $email, array $data, array $extra, SignIn $signIn, Dispatcher $events): User
    {
        $vouched = $profile->emailVerified && $provider->trustsVerifiedEmail()
            && $profile->email !== null && User::normaliseEmail($profile->email) === $email;

        $user = User::query()->create([
            'email' => $email,
            'first_name' => self::text($data['first_name'] ?? null) ?? self::shown('first_name', $profile->firstName),
            'last_name' => self::text($data['last_name'] ?? null) ?? self::shown('last_name', $profile->lastName),
            'phone' => self::text($data['phone'] ?? null) ?? self::shown('phone', $profile->phone),
            'locale' => app(Locales::class)->current(),
            'extra' => $extra === [] ? null : $extra,
        ]);

        if ($vouched) {
            $user->forceFill(['email_verified_at' => Date::now()])->save();
        }

        $this->accounts->link($user, $provider, $profile, tell: false);

        if (! $vouched) {
            $user->sendEmailVerificationNotification();
        }

        $id = (int) $user->getKey();
        $events->dispatch(new VisitorRegistered($id, $provider->key()));
        $signIn->in($request, $user);
        $events->dispatch(new VisitorSignedIn($id, 'registration'));

        return $user;
    }

    /**
     * What «Almost done» asks of the built-in fields, with the rules: the address when the
     * provider gave none, and a field the settings require that the provider left empty.
     *
     * @return array<string, list<mixed>>
     */
    private function asked(SocialProfile $profile): array
    {
        $asked = [];
        $given = ['first_name' => $profile->firstName, 'last_name' => $profile->lastName, 'phone' => $profile->phone];

        foreach (RegisterController::rules() as $name => $rules) {
            if (array_key_exists($name, $given) && RegisterController::asked()[$name] === Requirement::Required && $given[$name] === null) {
                $asked[$name] = $rules;
            }
        }

        if ($profile->email === null) {
            $asked['email'] = ['required', 'string', 'email', 'max:191'];
        }

        return $asked;
    }

    /** @return list<string> the fields of the site the registration requires */
    private function requiredFields(): array
    {
        $controls = app(FieldValues::class)->controls(Placement::Registration);

        return array_values(array_map(static fn (array $control): string => $control['name'], array_filter($controls, static fn (array $control): bool => $control['required'])));
    }

    /** @return array{0: array{provider: string, profile: SocialProfile, account: ?int, back: ?string}|null, 1: ?Provider} */
    private function pending(Request $request): array
    {
        $pending = (new SocialSession($request->session()))->waiting();

        if ($pending === null || $pending['account'] !== null) {
            return [null, null];
        }

        return [$pending, $this->keys->find($pending['provider'])];
    }

    private function failed(SocialSession $session, string $to): RedirectResponse
    {
        $session->notice(trans('webx-users::auth.social.failed'));

        return new RedirectResponse($to);
    }

    /** By name: behind the language prefix the first parameter of the route is the language. */
    private static function provider(Request $request): string
    {
        $provider = $request->route('provider');

        return is_string($provider) ? $provider : '';
    }

    /** A value the provider gave, where the settings do not hide the field. */
    private static function shown(string $name, ?string $value): ?string
    {
        return RegisterController::asked()[$name] === Requirement::Hidden ? null : self::text($value);
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && ($value = trim($value)) !== '' ? $value : null;
    }
}
