<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebxUi\Localization\Locales;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Fields;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Rules\ValidJsonLd;
use WebxUi\Seo\Rules\ValidRegex;
use WebxUi\Seo\Screens\SeoFieldType;

/**
 * One rule for one address, or for a shape of them.
 *
 * A `PUT` replaces the whole rule rather than patching it: the form edits every field at once,
 * and a half-sent record is harder to reason about than a resent one.
 */
final class SeoUrlRequest extends FormRequest
{
    /** Fields kept as a language map — the same set the card and the tables hold. */
    public const TRANSLATED = Fields::TRANSLATED;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'match_type' => ['required', Rule::in(UrlMatcher::types())],
            'pattern' => ['required', 'string', 'max:2048'],
            // A small signed integer, because the column is one. The panel offers a handful of
            // steps; the number matters only next to another rule's.
            'priority' => ['nullable', 'integer', 'between:-32768,32767'],
            'og_image' => ['nullable', 'array:path,alt,title,url'],
            'og_image.path' => ['required_with:og_image', 'string', 'max:2048'],
            'canonical' => ['nullable', 'string', 'max:2048'],
            // Comma-separated meta directives — `noindex, nofollow`, `max-snippet:20`. Not the
            // same thing as robots.txt, which is one setting for the whole site.
            'robots' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9:,\s_-]+$/i'],
            'json_ld' => ['nullable', new ValidJsonLd],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($this->input('match_type') === UrlMatcher::REGEX) {
            $rules['pattern'][] = new ValidRegex;
        }

        foreach (self::TRANSLATED as $field) {
            $rules[$field] = ['nullable', 'array'];
            $rules[$field.'.*'] = ['nullable', 'string', 'max:'.self::length($field)];
        }

        return $rules;
    }

    /**
     * What goes into the model: the address spelled one way, the text kept only for languages
     * the site actually publishes in, and the JSON-LD decoded.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $pattern = (string) $this->string('pattern');

        $values = [
            // A regular expression is text, not an address — normalising it would eat the
            // trailing `$` of `#^/catalog/(.+)/$#`.
            'match_type' => (string) $this->string('match_type'),
            'pattern' => $this->input('match_type') === UrlMatcher::REGEX ? $pattern : UrlNormaliser::normalise($pattern),
            'priority' => (int) $this->input('priority', 0),
            'og_image' => $this->storedImage(),
            'canonical' => $this->text('canonical'),
            'robots' => $this->text('robots'),
            'json_ld' => ValidJsonLd::decode($this->input('json_ld')),
            'is_active' => $this->boolean('is_active', true),
        ];

        foreach (self::TRANSLATED as $field) {
            $values[$field] = $this->translations($field);
        }

        return $values;
    }

    /**
     * A language the site does not publish in is not a language: the panel can only show the
     * ones it knows, and a key from somewhere else would sit in the column forever.
     *
     * @return array<string, string>
     */
    private function translations(string $field): array
    {
        $value = $this->input($field);

        if (! is_array($value)) {
            return [];
        }

        $codes = array_flip(app(Locales::class)->codes());
        $kept = [];

        foreach (array_intersect_key($value, $codes) as $code => $text) {
            $text = is_string($text) ? trim($text) : '';

            if ($text !== '') {
                $kept[(string) $code] = $text;
            }
        }

        return $kept;
    }

    /**
     * @return array<string, string>|null
     */
    private function storedImage(): ?array
    {
        $value = $this->input('og_image');

        if (! is_array($value) || ! is_string($value['path'] ?? null) || $value['path'] === '') {
            return null;
        }

        // The address is worked out on every read, so storing it would freeze a library that
        // moved to another disk — the same bargain `wx-media` makes in the settings.
        $stored = ['path' => $value['path']];

        foreach (['alt', 'title'] as $caption) {
            if (is_string($value[$caption] ?? null) && $value[$caption] !== '') {
                $stored[$caption] = $value[$caption];
            }
        }

        return $stored;
    }

    private function text(string $key): ?string
    {
        $value = $this->input($key);
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }

    /** The width of the column behind the field, spelled once, by the type that owns the card. */
    private static function length(string $field): int
    {
        return SeoFieldType::LIMITS[$field];
    }
}
