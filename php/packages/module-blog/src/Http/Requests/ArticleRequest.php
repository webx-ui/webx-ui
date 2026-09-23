<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Starting an article: a title, and the address made out of it.
 *
 * One language, like `module-pages`: an article is translated in its own form, field beside
 * field, and a dialog that asked for ten titles before the article exists would be a worse way
 * to start one. Everything else an article has is edited on the described screen and checked
 * against it (`ArticleForm`), so there is nothing else to declare here.
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[\p{L}\p{N}]+(?:[-_][\p{L}\p{N}]+)*$/u'],
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
}
