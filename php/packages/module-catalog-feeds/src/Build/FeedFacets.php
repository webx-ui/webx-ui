<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\TypedFacet;
use WebxUi\Catalog\Models\Product;

/**
 * What the selection of a feed may name (§5.1 of the feeds spec), for the form: every facet of the
 * site with the values its products have, a property outside the storefront's filter included. A
 * satellite that is not installed has no facet here, and so no control in the form.
 *
 * The category is a tree the form already holds, so it is not among them.
 */
final readonly class FeedFacets
{
    private const CATEGORY = 'category';

    /** A filter holds the code of a label, not its id ({@see FeedSelection}). */
    private const LABEL = 'label';

    /** A list a person can read through; the rest of a long one is typed in by its value. */
    private const VALUES = 500;

    public function __construct(private Facets $facets) {}

    /**
     * @return list<array{key: string, label: string, kind: string, typed: bool, values: list<array{value: string, label: string}>}>
     */
    public function offered(string $locale): array
    {
        $offered = [];

        foreach ($this->facets->selectable($locale) as $key => $facet) {
            if ($key === self::CATEGORY) {
                continue;
            }

            $typed = $facet instanceof TypedFacet;
            $listed = ! $typed && in_array($facet->kind(), [FacetKind::Terms, FacetKind::Tree], true);

            $offered[] = [
                'key' => (string) $key,
                'label' => $facet->label(),
                'kind' => $facet->kind()->value,
                'typed' => $typed,
                'values' => $listed ? $this->values($facet, $locale) : [],
            ];
        }

        return $offered;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function values(Facet $facet, string $locale): array
    {
        $products = Product::query()->toBase()->select('catalog_products.id')->whereNull('catalog_products.deleted_at');
        $values = $facet->sqlValues($products)->distinct()->limit(self::VALUES)->pluck('value')->map(strval(...))->all();
        $labels = $facet->labels($values, $locale);
        $codes = $facet->key() === self::LABEL ? $facet->slugs($values, $locale) : [];

        $rows = array_map(static fn (string $value): array => [
            'value' => (string) ($codes[$value] ?? $value),
            'label' => (string) ($labels[$value] ?? $value),
        ], $values);

        usort($rows, static fn (array $one, array $other): int => strnatcasecmp($one['label'], $other['label']));

        return $rows;
    }
}
