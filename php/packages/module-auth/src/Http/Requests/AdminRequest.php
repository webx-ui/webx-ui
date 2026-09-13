<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use WebxUi\Auth\Models\CmsUser;

/**
 * Creating an administrator and editing one, which differ in exactly two places: the address
 * has to be free of everybody *else*, and the password is required only the first time.
 */
final class AdminRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $existing = $this->route('admin');
        $id = $existing instanceof CmsUser ? $existing->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('cms_users', 'email')->ignore($id),
            ],
            // Required on the way in, optional afterwards: an edit that leaves it blank is an
            // edit of everything else, not a request to blank the password.
            'password' => [
                $id === null ? 'required' : 'nullable',
                'string',
                Password::min(12),
            ],
            'avatar' => ['nullable', 'string', 'max:1024'],
            'is_active' => ['nullable', 'boolean'],
            'is_super' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string', 'max:12'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:cms_roles,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => (string) __('webx-auth::validation.name'),
            'email' => (string) __('webx-auth::validation.email'),
            'password' => (string) __('webx-auth::validation.password'),
            'roles' => (string) __('webx-auth::validation.roles'),
        ];
    }
}
