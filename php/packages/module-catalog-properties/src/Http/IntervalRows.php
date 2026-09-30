<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Http;

use Illuminate\Support\Facades\DB;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyInterval;

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
     * Write the table as it is (§7.3): a row with an id is that interval, one without is new, and one
     * left out is gone. The products of the property are marked — the intervals their numbers fall
     * into are in their documents. The rows are {@see check()}ed first by the caller.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public static function save(Property $owner, array $rows): void
    {
        DB::transaction(static function () use ($owner, $rows): void {
            $kept = [];

            foreach ($rows as $position => $row) {
                $interval = isset($row['id']) ? PropertyInterval::query()->where('property_id', $owner->id)->find((int) $row['id']) : null;
                $interval ??= new PropertyInterval(['property_id' => $owner->id]);
                $interval->fill([
                    'title' => self::map($row['title'] ?? null),
                    'slug' => self::map($row['slug'] ?? null),
                    'min' => $row['min'] ?? null,
                    'max' => $row['max'] ?? null,
                    'position' => $position,
                ]);
                $interval->save();
                $kept[] = $interval->id;
            }

            PropertyInterval::query()->where('property_id', $owner->id)->whereNotIn('id', $kept === [] ? [0] : $kept)->delete();
        });

        $owner->touchProducts();
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
