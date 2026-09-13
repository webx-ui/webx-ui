<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'exists:cms_roles,slug'],
            // A string rather than a boolean: absent means "both", and a boolean has no way to
            // say that.
            'active' => ['nullable', Rule::in(['yes', 'no'])],
            'sort' => ['nullable', Rule::in([
                'name', '-name', 'email', '-email', 'created_at', '-created_at',
                'last_login_at', '-last_login_at',
            ])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
