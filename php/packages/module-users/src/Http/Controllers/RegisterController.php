<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Visitors\Events\VisitorRegistered;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Localization\Locales;
use WebxUi\Users\Models\User;
use WebxUi\Users\SignIn;
use WebxUi\Users\Throttle;
use WebxUi\Users\Urls;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;

/**
 * `GET|POST /account/register` (spec §6.2): the visitor is signed in the moment the form is sent
 * — confirming the address does not stand in the way (decision 9). An address already taken is
 * said so plainly (decision 15); the limit of registrations per IP keeps that from listing the
 * customers (§15). Closed registration (`webx-users.registration`) is a 404.
 */
final readonly class RegisterController
{
    public function __construct(
        private Auth $auth,
        private Urls $urls,
    ) {}

    public function show(Request $request, Views $views): View|RedirectResponse
    {
        abort_unless(SignIn::registrationOpen(), 404);

        $back = $this->urls->local($request->query(Urls::BACK));

        if ($this->auth->guard(Urls::guard())->check()) {
            return new RedirectResponse($back ?? $this->urls->home());
        }

        return $views->make('webx-users::register', [
            'action' => $this->urls->to('register'),
            // The browser checks what it can read: a length, not the rule object of the server.
            'rules' => FormRules::for([...self::rules(), 'password' => ['required', 'string', 'min:'.self::minimum()]], self::attributes()),
            'back' => $back,
            'min' => self::minimum(),
            'signIn' => $this->urls->to('sign-in', $back),
        ]);
    }

    public function store(Request $request, Throttle $throttle, Dispatcher $events, SignIn $signIn, Locales $locales): FormResponse
    {
        abort_unless(SignIn::registrationOpen(), 404);

        $throttle->register((string) $request->ip());

        $data = $request->validate(self::rules(), [], self::attributes());
        $email = User::normaliseEmail((string) $data['email']);

        if (User::query()->byEmail($email)->exists()) {
            throw ValidationException::withMessages(['email' => trans('webx-users::auth.taken')]);
        }

        $user = User::query()->create([
            'email' => $email,
            'password' => (string) $data['password'],
            'first_name' => self::text($data['first_name'] ?? null),
            'last_name' => self::text($data['last_name'] ?? null),
            'phone' => self::text($data['phone'] ?? null),
            'locale' => $locales->current(),
        ]);

        $id = (int) $user->getKey();
        $events->dispatch(new VisitorRegistered($id, 'password'));
        $signIn->in($request, $user);
        $events->dispatch(new VisitorSignedIn($id, 'registration'));

        return FormResponse::redirect($this->urls->local($request->input(Urls::BACK)) ?? $this->urls->home());
    }

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9][0-9 ()\-]{4,30}$/'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'max:255', self::password()],
        ];
    }

    /** `Password::min()` with what the site asked for in `webx-users.password_rules` (spec §5). */
    public static function password(): Password
    {
        /** @var array<string, mixed> $rules */
        $rules = (array) config('webx-users.password_rules', []);
        $password = Password::min(self::minimum());

        if ((bool) ($rules['mixed'] ?? false)) {
            $password->mixedCase();
        }

        if ((bool) ($rules['numbers'] ?? false)) {
            $password->numbers();
        }

        if ((bool) ($rules['symbols'] ?? false)) {
            $password->symbols();
        }

        if ((bool) ($rules['uncompromised'] ?? false)) {
            $password->uncompromised();
        }

        return $password;
    }

    public static function minimum(): int
    {
        $min = config('webx-users.password_rules.min');

        return is_numeric($min) ? max(1, (int) $min) : 8;
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'first_name' => trans('webx-users::auth.fields.first_name'),
            'last_name' => trans('webx-users::auth.fields.last_name'),
            'phone' => trans('webx-users::auth.fields.phone'),
            'email' => trans('webx-users::auth.fields.email'),
            'password' => trans('webx-users::auth.fields.password'),
        ];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && ($value = trim($value)) !== '' ? $value : null;
    }
}
