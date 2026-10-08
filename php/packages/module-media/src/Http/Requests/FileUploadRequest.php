<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebxUi\Media\Http\Controllers\FileController;
use WebxUi\Media\Rules\WithinPixelBudget;

final class FileUploadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxFiles = (int) config('webx-media.upload.max_files', 20);

        return [
            'directory_id' => ['required', 'integer', 'exists:media_directories,id'],
            'files' => ['required', 'array', 'max:'.$maxFiles],
            'files.*' => self::fileRules(),
        ];
    }

    /**
     * What one file has to be, whichever way it arrived: in this request, or a piece at a time
     * and claimed whole ({@see FileController::storeChunked}).
     *
     * @return list<mixed>
     */
    public static function fileRules(): array
    {
        return [
            'required',
            'file',
            'max:'.(int) config('webx-media.upload.max_size', 51200),
            // `mimes` rather than `mimetypes`: it is written in extensions, which is what the
            // configuration and the refusal both say, and it still checks the file's real type
            // rather than trusting its name.
            'mimes:'.implode(',', self::extensions()),
            new WithinPixelBudget,
        ];
    }

    /**
     * The refusals of {@see fileRules()} for a file under `$attribute`.
     *
     * @return array<string, string>
     */
    public static function fileMessages(string $attribute): array
    {
        return [
            "{$attribute}.mimes" => (string) __('webx-media::errors.unsupported-type', [
                'types' => implode(', ', self::extensions()),
            ]),
            "{$attribute}.max" => (string) __('webx-media::errors.file-too-large', [
                'size' => round(((int) config('webx-media.upload.max_size', 51200)) / 1024),
            ]),
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
            ...self::fileMessages('files.*'),
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
    private static function extensions(): array
    {
        /** @var list<string> $extensions */
        $extensions = (array) config('webx-media.upload.extensions', []);

        return $extensions;
    }
}
