<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use WebxUi\Auth\Models\CmsUser;

/**
 * What somebody may change about themselves.
 *
 * Deliberately less than `AdminRequest`: what a person is *allowed* to do is roles, being a
 * super administrator and being active at all, and none of those are theirs to grant. Nor is
 * the address they sign in with, which has its own door (`EmailChangeController`) because a
 * new one has to be proven at the new address before it takes effect.
 *
 * Anything else sent here is refused by name rather than dropped (WEBX_UI_PERMISSIONS.md §8):
 * a client that sends `roles` to its own profile is either confused or probing, and either
 * way it should hear "no" instead of a 200 that reads like a yes.
 *
 * The current password is asked for before a new one is accepted. Not to prove who is typing —
 * the session already did that — but because a session left open on a machine somebody else
 * uses should not be enough to lock its owner out of their own panel.
 */
final class ProfileRequest extends FormRequest
{
    /** The fields of a profile, and nothing else. */
    public const FIELDS = ['name', 'avatar', 'locale', 'password', 'current_password'];

    /** What the framework itself may add: a method override and the CSRF token of a form post. */
    private const TRANSPORT = ['_method', '_token'];

    public function authorize(): bool
    {
        return $this->user() instanceof CmsUser;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:1024'],
            'locale' => ['nullable', 'string', 'max:12'],
            // Blank means "leave it alone", the same as it does on the administrators screen.
            'password' => ['nullable', 'string', Password::min(12)],
            'current_password' => [
                Rule::requiredIf(fn (): bool => $this->filled('password')),
                'nullable',
                'string',
                'current_password:'.(string) config('webx-auth.guard'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $extra = array_diff(array_keys($this->all()), [...self::FIELDS, ...self::TRANSPORT]);

            foreach ($extra as $field) {
                $validator->errors()->add(
                    (string) $field,
                    (string) trans('webx-auth::profile.not-yours', ['field' => $field]),
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => (string) trans('webx-auth::profile.wrong-password'),
        ];
    }
}
