<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebxUi\Media\Rules\WithinPixelBudget;

final class FileUploadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var list<string> $mimes */
        $mimes = (array) config('webx-media.upload.mimes', []);
        $maxSize = (int) config('webx-media.upload.max_size', 51200);
        $maxFiles = (int) config('webx-media.upload.max_files', 20);

        return [
            'directory_id' => ['required', 'integer', 'exists:media_directories,id'],
            'files' => ['required', 'array', 'max:'.$maxFiles],
            'files.*' => [
                'required',
                'file',
                'max:'.$maxSize,
                // A white list: a list of what must not be uploaded is always missing one, and
                // the one it misses is usually executable.
                'mimetypes:'.implode(',', $mimes),
                new WithinPixelBudget,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'directory_id' => (string) __('webx-media::validation.directory_id'),
            'files' => (string) __('webx-media::validation.files'),
        ];
    }
}
