<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Catalog;

use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Localization\Locales;

/**
 * The words a set suggests for its landing (§8.2–8.3 of the landings spec): the slug of the base
 * and the slugs of the values — `{category}-{value}` — and the same with their names for `name`
 * and H1. The form shows the suggestion under the address; the generation fills its templates
 * with the same pieces, so the two never disagree on what a landing "should" be called.
 *
 * A range has no slug and no name of its own and is left out; the whole catalogue as the base
 * gives `{category}` nothing, and the dashes it would leave are trimmed.
 */
final class LandingWords
{
    public function __construct(
        private readonly Facets $facets,
        private readonly Locales $locales,
    ) {}

    /**
     * The slug the template gives this base and set, per language; a language where a piece has
     * no slug is left out.
     *
     * @return array<string, string>
     */
    public function slugs(?Category $category, LandingSet $set, string $template = '{category}-{value}'): array
    {
        $result = [];

        foreach ($this->locales->codes() as $locale) {
            $pieces = $this->pieces($set, $locale, slugs: true);

            if ($pieces === null) {
                continue;
            }

            $base = $category === null ? '' : (string) $category->getTranslation('slug', $locale, false);

            if ($category !== null && $base === '') {
                continue;
            }

            $slug = self::fill($template, $base, implode('-', $pieces));
            $slug = trim((string) preg_replace('/-{2,}/', '-', $slug), '-');

            if ($slug !== '') {
                $result[$locale] = $slug;
            }
        }

        return $result;
    }

    /**
     * A name or a heading from a template, per language: `{category} {value}`.
     *
     * @return array<string, string>
     */
    public function names(?Category $category, LandingSet $set, string $template = '{category} {value}'): array
    {
        $result = [];

        foreach ($this->locales->codes() as $locale) {
            $pieces = $this->pieces($set, $locale, slugs: false);

            if ($pieces === null) {
                continue;
            }

            $base = $category === null ? '' : $category->displayName($locale);
            $name = trim((string) preg_replace('/\s{2,}/u', ' ', self::fill($template, $base, implode(', ', $pieces))));

            if ($name !== '') {
                $result[$locale] = $name;
            }
        }

        return $result;
    }

    /** `{category}` and `{value}` replaced; whatever else the template says is kept. */
    public static function fill(string $template, string $category, string $value): string
    {
        return strtr($template, ['{category}' => $category, '{value}' => $value]);
    }

    /**
     * The slugs (or names) of the set's values in this language, facet by facet; null when a
     * value has none in it.
     *
     * @return list<string>|null
     */
    private function pieces(LandingSet $set, string $locale, bool $slugs): ?array
    {
        $pieces = [];

        foreach ($set->all() as $key => $choice) {
            $facet = $this->facets->find($key);

            if ($facet === null || $facet->kind() === FacetKind::Range) {
                continue;
            }

            $values = $choice['values'] ?? [];
            $words = $slugs ? $facet->slugs($values, $locale) : $facet->labels($values, $locale);

            foreach ($values as $value) {
                $word = trim((string) ($words[$value] ?? ''));

                if ($word === '') {
                    return null;
                }

                $pieces[] = $word;
            }
        }

        return $pieces === [] ? null : $pieces;
    }
}
