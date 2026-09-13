<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FileRenameRequest extends FormRequest
{
    /**
     * Only the displayed name. The key on the disk is fixed at upload, which is what keeps an
     * address already written into an article working after a rename.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => (string) __('webx-media::validation.title')];
    }
}
