<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DirectoryUpdateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['title' => (string) __('webx-media::validation.title')];
    }
}
