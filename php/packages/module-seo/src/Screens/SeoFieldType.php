<?php

declare(strict_types=1);

namespace WebxUi\Seo\Screens;

use Closure;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Localization\Locales;
use WebxUi\Seo\Fields;
use WebxUi\Seo\Rules\ValidJsonLd;

/**
 * `wx-seo` on the server: everything a page says about itself, as one value.
 *
 * One field rather than a dozen because that is how SEO is edited and how it is stored — the
 * card is a unit, the row behind it is a unit, and a screen that named twelve fields would have
 * to name them again in every entity that grows a card.
 *
 * The text inside is per language, but the node itself is not marked `localized`: the language
 * chips live inside the card, where they can sit on the three fields that need them and stay
 * off the picture, which has no languages.
 */
final class SeoFieldType implements FieldType
{
    /**
     * How long a field may be, in characters. Hard limits, not the soft ones the card counts
     * with: these are the width of the columns behind them.
     *
     * @var array<string, int>
     */
    public const LIMITS = [
        'title' => 255,
        'h1' => 255,
        'description' => 1000,
        'keywords' => 500,
        'og_title' => 255,
        'og_description' => 1000,
    ];

    public function __construct(
        private readonly FieldTypes $types,
        private readonly Locales $locales,
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return [
            'nullable',
            'array',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value)) {
                    return;
                }

                $validator = $this->validator->make($value, $this->inner(), [], $this->names());

                foreach ($validator->errors()->all() as $message) {
                    $fail($message);
                }
            },
        ];
    }

    /**
     * What is kept: the text of the languages the site publishes in, the picture as its key,
     * the structured data decoded, and nothing that was left blank.
     *
     * Blank is dropped rather than stored as an empty string because the merge reads a missing
     * field as "nothing said here" and an empty one the same way — but only one of the two
     * survives a round trip through the form without growing a row of nulls.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function store(mixed $value, array $node): array
    {
        if (! is_array($value)) {
            return [];
        }

        $stored = [];

        foreach (Fields::TRANSLATED as $field) {
            $translations = $this->translations($value[$field] ?? null);

            if ($translations !== []) {
                $stored[$field] = $translations;
            }
        }

        $image = $this->image($value['og_image'] ?? null);

        if ($image !== null) {
            $stored['og_image'] = $image;
        }

        foreach (['canonical', 'robots'] as $field) {
            $text = $this->text($value[$field] ?? null);

            if ($text !== null) {
                $stored[$field] = $text;
            }
        }

        $blocks = ValidJsonLd::decode($value['json_ld'] ?? null);

        if ($blocks !== null) {
            $stored['json_ld'] = $blocks;
        }

        return $stored;
    }

    /**
     * What the site reads: the same object with the picture's address filled in.
     *
     * The text is left as language maps. Unlike every other field type, this value is not one
     * thing in one language — it is a dozen, each with its own — and picking a language here
     * would have to pick it for all of them. `SeoData` is where a language is chosen, and it
     * is built from the model rather than from here.
     *
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        if (! is_array($stored) || $stored === []) {
            return $stored;
        }

        $image = $stored['og_image'] ?? null;

        if (is_array($image) && $image !== []) {
            $resolved = $this->types->get('wx-media')?->resolve($image, ['type' => 'wx-media'], $locale);
            $stored['og_image'] = $image + ['url' => is_array($resolved) ? ($resolved['url'] ?? null) : null];
        }

        return $stored;
    }

    /**
     * The rules of the object's own keys, checked as a form of their own — the only way to say
     * anything about a value whose shape is nested.
     *
     * @return array<string, mixed>
     */
    private function inner(): array
    {
        $rules = [
            'og_image' => ['nullable', 'array'],
            'og_image.path' => ['required_with:og_image', 'string', 'max:2048'],
            'canonical' => ['nullable', 'string', 'max:2048'],
            // Comma-separated meta directives — `noindex, nofollow`, `max-snippet:20`. Not the
            // same thing as robots.txt, which is one setting for the whole site.
            'robots' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9:,\s_-]+$/i'],
            'json_ld' => ['nullable', new ValidJsonLd],
        ];

        foreach (Fields::TRANSLATED as $field) {
            $rules[$field] = ['nullable', 'array'];
            $rules[$field.'.*'] = ['nullable', 'string', 'max:'.self::LIMITS[$field]];
        }

        return $rules;
    }

    /**
     * The words a message uses for these fields — the card's own labels, so an editor reads
     * "The canonical address is too long" rather than a column name.
     *
     * @return array<string, string>
     */
    private function names(): array
    {
        $names = [];

        foreach ([...Fields::TRANSLATED, 'canonical', 'robots', 'json_ld'] as $field) {
            $key = 'webx-seo::card.'.str_replace('_', '-', $field);
            $label = __($key);
            $names[$field] = is_string($label) && $label !== $key ? $label : $field;
            $names[$field.'.*'] = $names[$field];
        }

        $names['og_image'] = (string) __('webx-seo::card.og-image');
        $names['og_image.path'] = $names['og_image'];

        return $names;
    }

    /**
     * A language the site does not publish in is not a language: the card can only show the
     * ones it knows, and a key from somewhere else would sit in the column forever.
     *
     * @return array<string, string>
     */
    private function translations(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $kept = [];

        foreach (array_intersect_key($value, array_flip($this->locales->codes())) as $code => $text) {
            $text = $this->text($text);

            if ($text !== null) {
                $kept[(string) $code] = $text;
            }
        }

        return $kept;
    }

    /**
     * The library key and the captions, never the address: a library that moves from a public
     * directory to S3 must not have to rewrite a single page.
     *
     * @return array<string, string>|null
     */
    private function image(mixed $value): ?array
    {
        if (! is_array($value) || ! is_string($value['path'] ?? null) || $value['path'] === '') {
            return null;
        }

        $stored = ['path' => $value['path']];

        foreach (['alt', 'title'] as $caption) {
            if (is_string($value[$caption] ?? null) && $value[$caption] !== '') {
                $stored[$caption] = $value[$caption];
            }
        }

        return $stored;
    }

    private function text(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }
}
