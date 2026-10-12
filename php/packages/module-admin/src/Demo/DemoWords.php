<?php

declare(strict_types=1);

namespace WebxUi\Admin\Demo;

use WebxUi\Localization\Locales;

/**
 * The words of a demo document, in the languages the site publishes in.
 *
 * A document may give a text as one string — the site's default language, as every demo did
 * before translations — or as a map of languages, `{ "en": …, "ru": …, "pl": … }`. A map keeps
 * the languages the site has and drops the rest: a site in English and German has no use for the
 * Polish. When the site's default language is not among them, the document's own language (its
 * English, else its first) stands in for it, exactly as the one string did — a site whose default
 * is German still gets a demo it can read, rather than pages with no title in their main language.
 */
final class DemoWords
{
    /** The language every demo document is written in first. */
    public const SOURCE = 'en';

    public function __construct(private readonly Locales $locales) {}

    /**
     * One text as the map a translatable column keeps, or null for nothing to say.
     *
     * @return array<string, string>|null
     */
    public function of(mixed $text): ?array
    {
        if (is_string($text)) {
            $text = trim($text);

            return $text === '' ? null : [$this->locales->defaultCode() => $text];
        }

        if (! $this->isWords($text)) {
            return null;
        }

        /** @var array<string, string> $text */
        $given = array_filter(array_map(trim(...), $text), static fn (string $one): bool => $one !== '');

        if ($given === []) {
            return null;
        }

        $codes = $this->locales->codes();
        $default = $this->locales->defaultCode();
        $kept = array_intersect_key($given, array_flip($codes));

        if (! isset($kept[$default])) {
            $kept = [$default => $given[self::SOURCE] ?? reset($given)] + $kept;
        }

        return $kept;
    }

    /**
     * The same for every map inside a block tree — a field's value, a picture's alt text, a
     * repeater row's label — whatever its depth. Everything else is left as it is, plain strings
     * included: a field that is not localized keeps one value.
     */
    public function in(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($this->isWords($value)) {
            return $this->of($value);
        }

        return array_map($this->in(...), $value);
    }

    /**
     * A map of languages to strings, by its shape: the keys are languages — the site's, or ones
     * the catalogue knows — and the document's own language among them. The second part keeps a
     * picture or a link, whose keys are words like `path` and `url`, from ever being taken for one.
     */
    private function isWords(mixed $value): bool
    {
        if (! is_array($value) || ! array_key_exists(self::SOURCE, $value) || ! $this->locales->isMap($value)) {
            return false;
        }

        foreach ($value as $one) {
            if (! is_string($one)) {
                return false;
            }
        }

        return true;
    }
}
