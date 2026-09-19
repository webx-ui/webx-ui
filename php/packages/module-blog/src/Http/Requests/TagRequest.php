<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Renaming a tag, or making one (§10).
 *
 * One language and one line, which is the whole difference from a rubric. A tag is a word: it
 * is renamed in place, in the list, in the language the panel is open in — so what arrives is a
 * string and not a map, and the other languages of the same tag are left exactly as they were.
 *
 * Renaming does **not** move the address. A word somebody spelled wrong this morning is renamed
 * a dozen times before lunch, and a tag whose address followed every one of them would leave a
 * trail of aliases behind a decision nobody made. The address is a field of its own, and
 * changing it is a separate thing to type.
 */
final class TagRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[\p{L}\p{N}]+(?:[-_][\p{L}\p{N}]+)*$/u'],
            'noindex' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.regex' => (string) __('webx-blog::errors.slug-shape')];
    }

    /**
     * What the tag is filled with — only the fields that travelled.
     *
     * The address is left alone when it did not, which is what makes a rename a rename.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $values = [];

        if ($this->has('title')) {
            $values['title'] = trim((string) $this->input('title', ''));
        }

        $slug = trim((string) $this->input('slug', ''));

        if ($slug !== '') {
            $values['slug'] = $slug;
        } elseif ($this->isMethod('POST')) {
            // Made out of the title, transliterated, the way every address in this package is.
            $values['slug'] = Str::slug((string) ($values['title'] ?? ''));
        }

        if ($this->has('noindex')) {
            $values['noindex'] = $this->boolean('noindex');
        }

        return $values;
    }
}
