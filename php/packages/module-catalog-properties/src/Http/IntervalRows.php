<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Http;

use WebxUi\CatalogProperties\Models\Property;

/**
 * The checks of a table of intervals sent at once: a slug `[a-z0-9-]` and one per language within
 * the property, and a low end below the high one where both are given. Intervals may overlap —
 * that is the admin's choice (§2 of the properties spec).
 */
final class IntervalRows
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<string>> field → messages
     */
    public static function check(array $rows): array
    {
        $errors = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            foreach (self::map($row['slug'] ?? null) ?? [] as $locale => $slug) {
                if (preg_match(Property::CODE, $slug) !== 1) {
                    $errors["intervals.{$index}.slug.{$locale}"][] = (string) __('webx-catalog-properties::errors.slug');
                } elseif (isset($seen[$locale][$slug])) {
                    $errors["intervals.{$index}.slug.{$locale}"][] = (string) __('webx-catalog-properties::errors.slug-taken');
                }

                $seen[$locale][$slug] = true;
            }

            $min = $row['min'] ?? null;
            $max = $row['max'] ?? null;

            if (is_numeric($min) && is_numeric($max) && (float) $min >= (float) $max) {
                $errors["intervals.{$index}.max"][] = (string) __('webx-catalog-properties::errors.interval-ends');
            }
        }

        return $errors;
    }

    /**
     * A map of languages as it is, a bare string in the panel's language; empty words dropped.
     *
     * @return array<string, string>|null
     */
    public static function map(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = [app()->getLocale() => $value];
        }

        if (! is_array($value)) {
            return null;
        }

        $map = [];

        foreach ($value as $locale => $words) {
            if (is_string($words) && trim($words) !== '') {
                $map[(string) $locale] = trim($words);
            }
        }

        return $map === [] ? null : $map;
    }
}
