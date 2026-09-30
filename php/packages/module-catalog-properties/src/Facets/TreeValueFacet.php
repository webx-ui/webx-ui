<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Facets;

use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\TreeFacet;
use WebxUi\CatalogProperties\Models\PropertyValue;

/**
 * A reference book of values in a tree — «Metal / Steel / Stainless» (§3.1 of the properties
 * spec): choosing a node means any value under it, a product counts under every ancestor of its
 * value, and a chosen ancestor takes its chosen descendants in, as the categories do.
 */
final class TreeValueFacet extends ValueFacet implements TreeFacet
{
    public function kind(): FacetKind
    {
        return FacetKind::Tree;
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string|null>
     */
    public function parents(array $values): array
    {
        $parents = [];

        foreach ($this->load($values) as $value) {
            $parents[(string) $value->id] = $value->parent_id === null ? null : (string) $value->parent_id;
        }

        return $parents;
    }

    public function normalise(FacetValue $value): FacetValue
    {
        if (count($value->values) < 2) {
            return $value;
        }

        $chosen = $this->load($value->values);
        $kept = [];

        foreach ($chosen as $node) {
            $covered = $chosen->contains(static fn (PropertyValue $other): bool => $other->id !== $node->id
                && $other->lft < $node->lft
                && $other->rgt > $node->rgt);

            if (! $covered) {
                $kept[] = (string) $node->id;
            }
        }

        return FacetValue::of($kept);
    }

    protected function narrow(QueryBuilder $rows, FacetValue $value): void
    {
        $ids = [];

        foreach ($this->load($value->values) as $node) {
            $ids = [...$ids, ...$node->subtreeIds()];
        }

        $rows->whereIn(FacetRows::TABLE.'.value_id', $ids === [] ? [0] : array_values(array_unique($ids)));
    }
}
