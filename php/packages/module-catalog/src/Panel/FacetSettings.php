<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Models\Category;

/**
 * A category's own facet settings — the rows of `catalog_category_facets` (§6.2).
 *
 * Read and written here, resolved elsewhere: which facets a category ends up showing (its own
 * rows, the nearest configured ancestor's, or every facet of the registry) is the engine's
 * question, asked with the registry in hand.
 */
final class FacetSettings
{
    /**
     * Null when the category inherits; otherwise the facets in order, shown or hidden.
     *
     * @return list<array{key: string, visible: bool}>|null
     */
    public static function of(Category $category): ?array
    {
        if (! $category->exists) {
            return null;
        }

        $rows = DB::table('catalog_category_facets')
            ->where('category_id', $category->getKey())
            ->orderBy('position')
            ->orderBy('id')
            ->get(['facet_key', 'is_visible']);

        if ($rows->isEmpty()) {
            return null;
        }

        return $rows->map(static fn (object $row): array => [
            'key' => (string) $row->facet_key,
            'visible' => (bool) $row->is_visible,
        ])->values()->all();
    }

    /**
     * Replace the settings; null goes back to inheriting.
     *
     * @param  list<array{key: string, visible: bool}>|null  $facets
     * @return array{field: string, from: mixed, to: mixed}|null What changed, for the journal.
     */
    public static function write(Category $category, ?array $facets): ?array
    {
        $before = self::of($category);

        DB::table('catalog_category_facets')->where('category_id', $category->getKey())->delete();

        if ($facets !== null && $facets !== []) {
            $rows = [];

            foreach (array_values($facets) as $position => $facet) {
                $rows[] = [
                    'category_id' => $category->getKey(),
                    'facet_key' => $facet['key'],
                    'is_visible' => $facet['visible'],
                    'position' => $position,
                ];
            }

            DB::table('catalog_category_facets')->insert($rows);
        }

        $after = self::of($category);

        return $before === $after ? null : [
            'field' => 'facets',
            'from' => self::describe($before),
            'to' => self::describe($after),
        ];
    }

    /**
     * How the journal shows a setting: the shown facets in order, or nothing for "inherited".
     *
     * @param  list<array{key: string, visible: bool}>|null  $facets
     */
    private static function describe(?array $facets): ?string
    {
        if ($facets === null) {
            return null;
        }

        $shown = array_map(
            static fn (array $facet): string => $facet['key'],
            array_filter($facets, static fn (array $facet): bool => $facet['visible']),
        );

        return implode(', ', $shown);
    }
}
