<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Mcp;

use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Filter\BaseFacets;
use WebxUi\Catalog\Filter\FacetSet;
use WebxUi\Catalog\Models\Category;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;

/**
 * A set in the words of an agent (§7 of the collections spec): a facet by its code, a value by its
 * slug, in the language the call names and then in any other. It is stored by ids, as the panel
 * stores it, and read back with both.
 *
 * Unlike a landing's, an empty set is legal: «New arrivals» has none (§3.5). A facet named with
 * nothing readable in it is not: {@see FacetSet} would drop it without a word, and the collection
 * the agent believes it narrowed would print the whole catalogue.
 */
final class CollectionSet
{
    public function __construct(
        private readonly BaseFacets $baseFacets,
        private readonly Facets $facets,
        private readonly Locales $locales,
    ) {}

    /**
     * A set as an agent writes it, as the panel stores it: keys and ids.
     *
     * @return array<string, array<string, mixed>>
     */
    public function stored(mixed $set, string $locale): array
    {
        if ($set === null || $set === []) {
            return [];
        }

        if (! is_array($set) || array_is_list($set)) {
            throw new ToolFailure('`set` must be an object: facet code → a list of value slugs, or { "min", "max" } for a range.');
        }

        $stored = [];

        foreach ($set as $code => $choice) {
            $facet = $this->facet((string) $code, $locale);

            if ($facet->kind() === FacetKind::Range) {
                $stored[$facet->key()] = $this->range($facet, $choice);

                continue;
            }

            $slugs = is_array($choice) ? array_map(self::word(...), array_values($choice)) : [self::word($choice)];

            if ($slugs === []) {
                throw new ToolFailure('«'.$facet->label().'» needs at least one value. catalog_collections_facets lists the values a base has.');
            }

            $stored[$facet->key()] = ['values' => $this->values($facet, $slugs, $locale)];
        }

        return $stored;
    }

    /**
     * A stored set as an agent reads it: each facet with its code and label, each value with its
     * id, slug and label.
     *
     * @return list<array<string, mixed>>
     */
    public function described(FacetSet $set, string $locale): array
    {
        $described = [];

        foreach ($set->all() as $key => $choice) {
            $facet = $this->facets->find($key);

            if ($facet === null) {
                $described[] = ['key' => $key, 'code' => null, 'gone' => true, ...$choice];

                continue;
            }

            $head = ['key' => $key, 'code' => $facet->code($locale), 'label' => $facet->label()];

            if ($facet->kind() === FacetKind::Range) {
                $described[] = [...$head, 'min' => $choice['min'] ?? null, 'max' => $choice['max'] ?? null];

                continue;
            }

            $ids = $choice['values'] ?? [];
            $slugs = $facet->slugs($ids, $locale);
            $labels = $facet->labels($ids, $locale);

            $described[] = [...$head, 'values' => array_map(static fn (string $id): array => [
                'id' => $id,
                'slug' => $slugs[$id] ?? null,
                'label' => $labels[$id] ?? null,
            ], $ids)];
        }

        return $described;
    }

    /**
     * What a base offers a set: the words of a set come from here.
     *
     * @return list<array<string, mixed>>
     */
    public function offered(?Category $category, string $locale): array
    {
        $offered = [];

        foreach ($this->baseFacets->of($category, $locale) as ['facet' => $facet, 'min' => $min, 'max' => $max, 'counts' => $counts]) {
            $head = ['key' => $facet->key(), 'code' => $facet->code($locale), 'label' => $facet->label(), 'kind' => $facet->kind()->value];

            if ($facet->kind() === FacetKind::Range) {
                $offered[] = [...$head, 'min' => $min, 'max' => $max];

                continue;
            }

            $ids = array_map(strval(...), array_keys($counts));
            $slugs = $facet->slugs($ids, $locale);
            $labels = $facet->labels($ids, $locale);

            $offered[] = [...$head, 'values' => array_map(static fn (string $id): array => [
                'id' => $id,
                'slug' => $slugs[$id] ?? null,
                'label' => $labels[$id] ?? null,
                'count' => (int) $counts[$id],
            ], $ids)];
        }

        return $offered;
    }

    /**
     * @return array{min: mixed, max: mixed}
     */
    private function range(Facet $facet, mixed $choice): array
    {
        $ends = is_array($choice) && ! array_is_list($choice) && array_diff_key($choice, ['min' => 0, 'max' => 0]) === []
            ? ['min' => $choice['min'] ?? null, 'max' => $choice['max'] ?? null]
            : null;

        $numbers = array_filter($ends ?? [], is_numeric(...));

        if ($numbers === [] || count($numbers) !== count(array_filter($ends ?? [], static fn (mixed $end): bool => $end !== null))) {
            throw new ToolFailure('«'.$facet->label().'» is a range: { "min": …, "max": … }, at least one end, each a number. '
                .'catalog_collections_facets gives its ends.');
        }

        return $ends;
    }

    /**
     * Slugs of a facet's values as its ids: in the call's language first, then in any other.
     *
     * @param  list<string>  $slugs
     * @return list<string>
     */
    private function values(Facet $facet, array $slugs, string $locale): array
    {
        $ids = [];
        $missing = $slugs;

        foreach ([$locale, ...array_diff($this->locales->codes(), [$locale])] as $language) {
            if ($missing === []) {
                break;
            }

            foreach ($facet->resolveSlugs($missing, $language) as $slug => $id) {
                $ids[(string) $slug] = (string) $id;
            }

            $missing = array_values(array_filter($missing, static fn (string $slug): bool => ! isset($ids[$slug])));
        }

        if ($missing !== []) {
            throw new ToolFailure('«'.$facet->label().'» has no value "'.implode('", "', $missing).'". catalog_collections_facets lists the values a base has.');
        }

        return array_values(array_unique(array_map(static fn (string $slug): string => $ids[$slug], $slugs)));
    }

    /** A facet by its code in the call's language, in any other, or by its key. */
    private function facet(string $code, string $locale): Facet
    {
        $facet = $this->facets->byCode($code, $locale);

        foreach ($this->locales->codes() as $language) {
            $facet ??= $this->facets->byCode($code, $language);
        }

        $facet ??= $this->facets->find($code);

        if ($facet === null) {
            throw new ToolFailure('There is no facet "'.$code.'". catalog_collections_facets lists the ones a base offers.');
        }

        if ($facet->key() === CategoryFacet::KEY) {
            throw new ToolFailure((string) __('webx-catalog-collections::errors.category-facet'));
        }

        return $facet;
    }

    /** A slug as a string: anything else is no slug, and is refused as one. */
    private static function word(mixed $slug): string
    {
        return is_scalar($slug) ? (string) $slug : '(not a slug)';
    }
}
