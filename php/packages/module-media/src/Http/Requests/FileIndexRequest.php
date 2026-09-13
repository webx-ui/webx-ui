<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebxUi\Media\Support\MediaType;

final class FileIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Optional: without it the search covers the whole library, which is what a person
            // typing a name expects.
            'directory_id' => ['nullable', 'integer', 'exists:media_directories,id'],
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(MediaType::all())],
            'sort' => ['nullable', Rule::in([
                'name', '-name', 'created_at', '-created_at', 'size', '-size',
            ])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:96'],
        ];
    }
}
