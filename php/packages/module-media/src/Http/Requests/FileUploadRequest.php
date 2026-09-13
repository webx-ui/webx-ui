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
        $maxSize = (int) config('webx-media.upload.max_size', 51200);
        $maxFiles = (int) config('webx-media.upload.max_files', 20);

        return [
            'directory_id' => ['required', 'integer', 'exists:media_directories,id'],
            'files' => ['required', 'array', 'max:'.$maxFiles],
            'files.*' => [
                'required',
                'file',
                'max:'.$maxSize,
                // `mimes` rather than `mimetypes`: it is written in extensions, which is what
                // the configuration and the refusal both say, and it still checks the file's
                // real type rather than trusting its name.
                'mimes:'.implode(',', $this->extensions()),
                new WithinPixelBudget,
            ],
        ];
    }

    /**
     * The server's own words for a refusal.
     *
     * Laravel's default lists every mime type it was given, which arrives as a paragraph of
     * `application/vnd.openxmlformats-officedocument…` — true, and useless to the person
     * holding the file.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.*.mimes' => (string) __('webx-media::errors.unsupported-type', [
                'types' => implode(', ', $this->extensions()),
            ]),
            'files.*.max' => (string) __('webx-media::errors.file-too-large', [
                'size' => round(((int) config('webx-media.upload.max_size', 51200)) / 1024),
            ]),
            'files.max' => (string) __('webx-media::errors.too-many-files', [
                'count' => (int) config('webx-media.upload.max_files', 20),
            ]),
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

    /**
     * @return list<string>
     */
    private function extensions(): array
    {
        /** @var list<string> $extensions */
        $extensions = (array) config('webx-media.upload.extensions', []);

        return $extensions;
    }
}
