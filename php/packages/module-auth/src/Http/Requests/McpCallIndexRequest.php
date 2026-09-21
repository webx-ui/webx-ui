<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class McpCallIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // An administrator's id, or `none` for calls that acted as nobody — the stdio
            // server, where there is no request.
            'user' => ['nullable', 'string', 'regex:/^(\d+|none)$/'],
            'tool' => ['nullable', 'string', 'max:128'],
            'outcome' => ['nullable', Rule::in(['ok', 'failed', 'dry'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
