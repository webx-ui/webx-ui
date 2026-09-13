<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DirectoryMoveRequest extends FormRequest
{
    /**
     * `before_id` and `after_id` place the folder among its new siblings; without either it goes
     * last, which is what dropping onto a folder rather than between two of them means.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['required', 'integer', 'exists:media_directories,id'],
            'before_id' => ['nullable', 'integer', 'exists:media_directories,id', 'prohibits:after_id'],
            'after_id' => ['nullable', 'integer', 'exists:media_directories,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['parent_id' => (string) __('webx-media::validation.parent_id')];
    }
}
