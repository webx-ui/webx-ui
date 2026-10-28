<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Users\Account\AccountMenu;
use WebxUi\Users\Emails\EmailChange;
use WebxUi\Users\Fields\FieldValues;
use WebxUi\Users\Fields\Placement;
use WebxUi\Users\Models\User;
use WebxUi\Users\Urls;
use WebxUi\Widgets\Forms\FormResponse;
use WebxUi\Widgets\Forms\FormRules;

/**
 * `/account` and `/account/profile` (spec §11.1): the first name, the last name and the phone as
 * the settings ask for them (§10.5), the fields of the site with the place `profile` (§10.4) —
 * one the visitor may not change read-only — and the e-mail with its change (§8.5), which goes
 * through a letter to the new address and so is a form of its own.
 */
final readonly class ProfileController
{
    public function __construct(
        private Auth $auth,
        private Urls $urls,
    ) {}

    /** The first section of the menu: the profile, unless a module put one before it. */
    public function index(AccountMenu $menu): RedirectResponse
    {
        $first = $menu->for(self::user($this->auth)->toVisitor())[0] ?? null;

        return new RedirectResponse($this->urls->named($first === null ? 'webx-users.profile' : $first->route));
    }

    public function show(Request $request, Views $views, FieldValues $fields): View
    {
        $user = self::user($this->auth);
        $pending = $request->hasSession() ? $request->session()->get(EmailChange::PENDING) : null;

        return $views->make('webx-users::account.profile', [
            'user' => $user,
            'action' => $this->urls->to('profile.store'),
            'rules' => FormRules::for(
                [...self::rules(), ...$fields->formRules(Placement::Profile)],
                [...RegisterController::attributes(), ...$fields->attributes(Placement::Profile)],
            ),
            'asked' => RegisterController::asked(),
            'fields' => $fields->controls(Placement::Profile, $user),
            'email' => [
                'action' => $this->urls->to('email.store'),
                'password' => $user->password !== null,
                'pending' => is_string($pending) && $pending !== $user->email ? $pending : null,
            ],
        ]);
    }

    public function store(Request $request, FieldValues $fields): FormResponse
    {
        $user = self::user($this->auth);

        // Both halves before either answers: every error of the form at once.
        $errors = [];
        $data = [];
        $extra = [];

        try {
            $data = $request->validate(self::rules(), [], RegisterController::attributes());
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        try {
            $input = $request->input(FieldValues::INPUT);
            $extra = $fields->validate(is_array($input) ? $input : [], Placement::Profile, $user);
        } catch (ValidationException $exception) {
            $errors = [...$errors, ...$exception->errors()];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // A field the settings hide is not sent and keeps what it had.
        foreach (array_keys(self::rules()) as $name) {
            $user->setAttribute($name, self::text($data[$name] ?? null));
        }

        if ($extra !== []) {
            $user->extra = [...($user->extra ?? []), ...$extra];
        }

        $user->save();

        return FormResponse::message(trans('webx-users::account.profile.saved'));
    }

    /**
     * The built-in fields of the profile — the registration's, without the address and the
     * password, which have forms of their own.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return array_diff_key(RegisterController::rules(), ['email' => true, 'password' => true]);
    }

    /** Behind `webx.visitor`: somebody is signed in, and it is a visitor of this module. */
    public static function user(Auth $auth): User
    {
        $user = $auth->guard(Urls::guard())->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && ($value = trim($value)) !== '' ? $value : null;
    }
}
