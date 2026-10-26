<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebxUi\Media\Support\MediaAlt;

final class FileRenameRequest extends FormRequest
{
    /**
     * The displayed name and the alt, either or both. The key on the disk is fixed at upload,
     * which is what keeps an address already written into an article working after a rename.
     *
     * The alt is a map of languages; `null` or an empty map clears it, an empty language is
     * dropped — a picture nobody described is decorative, and that is a choice too.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [Rule::requiredIf(! $this->exists('alt')), 'string', 'max:255'],
            'alt' => ['sometimes', 'nullable', 'array'],
            'alt.*' => ['nullable', 'string', 'max:'.MediaAlt::MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => (string) __('webx-media::validation.title')];
    }
}
