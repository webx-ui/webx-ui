<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;

/**
 * The facets of the panel's list as a query string carries them — `facets[category][]=3`,
 * `facets[price][min]=100` — read the same way by the list, by a bulk action over "everything the
 * filter finds" and by an agent's `catalog_products_list`. A key the registry does not know is
 * dropped rather than obeyed.
 */
final class ChosenFacets
{
    public function __construct(private readonly Facets $facets) {}

    /**
     * @param  array<mixed>  $input
     * @return array<string, FacetValue>
     */
    public function read(array $input): array
    {
        $chosen = [];

        foreach ($input as $key => $value) {
            $facet = is_string($key) ? $this->facets->find($key) : null;

            if ($facet === null || ! is_array($value)) {
                continue;
            }

            $chosen[$key] = $facet->kind() === FacetKind::Range
                ? FacetValue::range(
                    is_numeric($value['min'] ?? null) ? (float) $value['min'] : null,
                    is_numeric($value['max'] ?? null) ? (float) $value['max'] : null,
                )
                : FacetValue::of(array_filter($value, static fn (mixed $one): bool => is_string($one) || is_int($one)));
        }

        return $chosen;
    }
}
