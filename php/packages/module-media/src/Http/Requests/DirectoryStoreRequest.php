<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DirectoryStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['required', 'integer', 'exists:media_directories,id'],
            'title' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_id' => (string) __('webx-media::validation.parent_id'),
            'title' => (string) __('webx-media::validation.title'),
        ];
    }
}
