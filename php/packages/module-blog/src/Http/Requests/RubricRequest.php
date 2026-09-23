<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use WebxUi\Admin\Screens\Types\RichTextType;
use WebxUi\Localization\Locales;

/**
 * A rubric as its form saves it (§10).
 *
 * Not a described screen, unlike the article's: nothing patches a rubric, and the form is six
 * fields beside a list that is dragged. What it does share with the described screens is the
 * shape of its translated values — a map of language to text — so that is what arrives here,
 * and a panel with one language sends a one-key map rather than a bare string.
 *
 * A bare string is accepted anyway. `useLocalized` switches itself off when the site publishes
 * in a single language, and a request class that only understood maps would refuse the very
 * panel most sites run (CLAUDE.md §4).
 *
 * Nothing here checks that the address is free: only the registry sees pages, articles, rubrics
 * and tags at once, and it answers by refusing the save with a 422 under `slug` (§4).
 */
final class RubricRequest extends FormRequest
{
    /** The fields that are a map of languages rather than a value (§9). */
    private const TRANSLATED = ['title', 'slug', 'lead'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'array'],
            'slug.*' => ['nullable', 'string', 'max:190', 'regex:/^[\p{L}\p{N}]+(?:[-_][\p{L}\p{N}]+)*$/u'],
            // A document rather than a line since the form grew a tab for it, so the limit is
            // the one every rich text field in the panel has. What may be inside it is not
            // checked here: the field type takes out everything it does not name, and the
            // controller is where a value goes through it.
            'lead' => ['nullable', 'array'],
            'lead.*' => ['nullable', 'string', 'max:'.RichTextType::MAX],
            // What `wx-media` holds is a library key; the column holds the id of a row, and the
            // controller is where one becomes the other.
            'cover' => ['nullable', 'array'],
            'cover.path' => ['nullable', 'string', 'max:1024'],
            'is_visible' => ['boolean'],
            // Checked by `module-seo`'s own field type when it is installed, and ignored when
            // it is not: a panel without SEO has no card to send.
            'seo' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.*.regex' => (string) __('webx-blog::errors.slug-shape')];
    }

    /**
     * Language maps, whatever shape they arrived in, and an address for every language that
     * was given a title without one.
     */
    protected function prepareForValidation(): void
    {
        $locale = $this->container->make(Locales::class)->defaultCode();
        $replace = [];

        foreach (self::TRANSLATED as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);
            $replace[$field] = is_array($value) ? $value : [$locale => $value];
        }

        // A dialog that asks for a name and nothing else still saves a rubric: the address is
        // made out of the title, language by language, exactly as an article's is.
        if (array_key_exists('title', $replace)) {
            $replace['slug'] = $this->withAddresses($replace['slug'] ?? [], $replace['title']);
        }

        if ($replace !== []) {
            $this->merge($replace);
        }
    }

    /**
     * The values the model is filled with — only the fields that travelled.
     *
     * A form that saved one tab must not empty another, and `is_visible` is the field this
     * matters for: absent means "leave it", not "hide the rubric".
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $values = [];

        foreach ([...self::TRANSLATED, 'is_visible'] as $field) {
            if ($this->has($field)) {
                $values[$field] = $field === 'is_visible' ? $this->boolean($field) : $this->input($field);
            }
        }

        return $values;
    }

    /** The library key of the picture, or null when the field travelled empty. */
    public function coverPath(): ?string
    {
        $path = $this->input('cover.path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * An address per language, made out of the title where the editor did not write one.
     *
     * `Str::slug` transliterates, so a Russian title gives a Latin address rather than a
     * percent-encoded one — which is what a site wants and what nobody wants to type by hand.
     *
     * @param  array<string, mixed>  $slugs
     * @param  array<string, mixed>  $titles
     * @return array<string, mixed>
     */
    private function withAddresses(array $slugs, array $titles): array
    {
        foreach ($titles as $code => $title) {
            $given = $slugs[$code] ?? null;

            if ((is_string($given) && trim($given) !== '') || ! is_string($title)) {
                continue;
            }

            $made = Str::slug($title);

            if ($made !== '') {
                $slugs[$code] = $made;
            }
        }

        return $slugs;
    }
}
