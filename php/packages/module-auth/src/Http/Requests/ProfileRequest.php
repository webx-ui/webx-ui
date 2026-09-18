<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use WebxUi\Auth\Models\CmsUser;

/**
 * What somebody may change about themselves.
 *
 * Deliberately less than `AdminRequest`: what a person is *allowed* to do is roles, being a
 * super administrator and being active at all, and none of those are theirs to grant. Nor is
 * the address they sign in with, which is a change that needs to be proven at the new address
 * before it takes effect — until that exists, the email stays where it is.
 *
 * The current password is asked for before a new one is accepted. Not to prove who is typing —
 * the session already did that — but because a session left open on a machine somebody else
 * uses should not be enough to lock its owner out of their own panel.
 */
final class ProfileRequest extends FormRequest
{
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
