<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ImageEditRequest extends FormRequest
{
    /**
     * Operations, not a finished picture.
     *
     * The editor's canvas works on a preview, so what it could send back is smaller than the
     * original — and a server that accepts an image and calls it an edit has no way to tell an
     * edit from a replacement.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'crop' => ['nullable', 'array'],
            'crop.x' => ['required_with:crop', 'integer', 'min:0'],
            'crop.y' => ['required_with:crop', 'integer', 'min:0'],
            'crop.width' => ['required_with:crop', 'integer', 'min:1'],
            'crop.height' => ['required_with:crop', 'integer', 'min:1'],
            'rotate' => ['nullable', 'integer', Rule::in([0, 90, 180, 270])],
            'flip' => ['nullable', Rule::in(['horizontal', 'vertical'])],
            'resize' => ['nullable', 'array'],
            'resize.width' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'resize.height' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
