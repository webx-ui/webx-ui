<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * What the section writes about an article, in the language the panel is open in.
 *
 * One language, like `module-pages`: an article is translated in its own form, field beside
 * field, and a dialog that asked for ten titles before the article exists would be a worse way
 * to start one.
 *
 * Nothing here checks that the address is free. Only the registry can answer that — it is the
 * one thing that sees pages, rubrics and tags at once — and it answers by refusing the save
 * (§4, `OnConflict::Fail`), which arrives under the `slug` field like any other error.
 */
final class ArticleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[\p{L}\p{N}]+(?:[-_][\p{L}\p{N}]+)*$/u'],
            'lead' => ['nullable', 'string', 'max:2000'],
            'cover_id' => ['nullable', 'integer', Rule::exists('media_files', 'id')],
            'author_id' => ['nullable', 'integer', Rule::exists('cms_users', 'id')],
            'pinned' => ['sometimes', 'boolean'],

            // Ids in the order they are to be kept: the first rubric is the main one (§2.6),
            // and the pivot's `position` is written from this order rather than sent with it.
            'rubrics' => ['sometimes', 'array'],
            'rubrics.*' => ['integer', Rule::exists('rubrics', 'id')],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
            'related' => ['sometimes', 'array'],
            'related.*' => ['integer', Rule::exists('articles', 'id')],

            // What the editor read, so a save can be refused rather than written over somebody
            // else's. A request that names none did not read the article first — an import, a
            // script — and is let through: the check protects an editor from a surprise, and
            // there is no editor to surprise.
            'revision' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.regex' => (string) __('webx-blog::errors.slug-shape')];
    }

    public function title(): string
    {
        return trim((string) $this->input('title', ''));
    }

    /**
     * The address, made out of the title when the editor did not write one.
     *
     * `Str::slug` transliterates, so a Russian title gives a Latin address rather than a
     * percent-encoded one — which is what a site wants and what nobody wants to type by hand.
     */
    public function slug(): string
    {
        $slug = trim((string) $this->input('slug', ''));

        return $slug !== '' ? $slug : Str::slug($this->title());
    }

    /**
     * The article's own columns, only the ones that were sent.
     *
     * "Only the ones that were sent" is the whole of it: the form saves one tab at a time, and
     * a field the other tab owns must not be emptied because it was not in the request.
     *
     * Not `attributes()`, which is `FormRequest`'s own and names fields for error messages —
     * a collision there is silent and turns every message into nonsense (CLAUDE.md §4).
     *
     * @return array<string, mixed>
     */
    public function columns(): array
    {
        $values = [];

        if ($this->has('title')) {
            $values['title'] = $this->title();
        }

        if ($this->has('slug') || $this->has('title')) {
            $values['slug'] = $this->slug();
        }

        foreach (['lead', 'cover_id', 'author_id', 'pinned'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $values[$field] = match ($field) {
                'pinned' => $this->boolean('pinned'),
                'cover_id', 'author_id' => $this->input($field) === null ? null : (int) $this->input($field),
                default => (string) $this->input($field, ''),
            };
        }

        return $values;
    }

    /**
     * The ids of one relation, in the order they arrived, or null when it was not sent at all.
     *
     * Null and an empty array are different answers: "leave the rubrics alone" and "this
     * article is in no rubric".
     *
     * @return list<int>|null
     */
    public function ids(string $relation): ?array
    {
        if (! $this->has($relation)) {
            return null;
        }

        $ids = $this->input($relation);

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $ids)));
    }

    public function revision(): ?string
    {
        $sent = $this->input('revision');

        return is_string($sent) ? $sent : null;
    }
}
