<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebxUi\Admin\Access\AccessPolicy;
use WebxUi\Auth\Models\Role;

/**
 * A role as the form sends it: a name, a code, and the permissions ticked.
 *
 * A permission has to be one an installed module declares — a typo in a role is a permission
 * nobody can check — except the ones the role already had: a module taken off the site leaves
 * its permissions in roles, and saving such a role for another reason must not fail over them.
 */
final class RoleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $existing = $this->route('role');
        $role = $existing instanceof Role ? $existing : null;
        $present = $role === null ? 'required' : 'sometimes';

        /** @var AccessPolicy $policy */
        $policy = app(AccessPolicy::class);
        $known = [...$policy->declared(), ...($role->permissions ?? [])];

        return [
            'name' => [$present, 'required', 'string', 'max:255'],
            // Made from the name when left out (RoleController): it is what MCP and the filters
            // name a role by, and nobody should have to think of one to make a role.
            'slug' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9-]*$/',
                Rule::unique('cms_roles', 'slug')->ignore($role?->getKey()),
            ],
            // Present rather than required: a role with nothing ticked yet is a role.
            'permissions' => [$role === null ? 'present' : 'sometimes', 'array'],
            'permissions.*' => [
                'string',
                'distinct',
                static function (string $attribute, mixed $value, Closure $fail) use ($known): void {
                    if (! in_array($value, $known, true)) {
                        $fail((string) __('webx-auth::validation.unknown-permission', ['permission' => (string) $value]));
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => (string) __('webx-auth::validation.name'),
            'slug' => (string) __('webx-auth::validation.slug'),
            'permissions' => (string) __('webx-auth::validation.permissions'),
        ];
    }
}
