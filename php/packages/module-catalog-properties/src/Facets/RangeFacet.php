<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Facets;

use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\IndexField;

/**
 * A number with a slider: `weight_1-2.5`. The engine answers the lowest and the highest; a range has
 * no page of its own, and cannot be given one (§5.1 of the properties spec).
 */
final class RangeFacet extends PropertyFacet
{
    public function kind(): FacetKind
    {
        return FacetKind::Range;
    }

    public function indexable(): bool
    {
        return false;
    }

    /** Its number in `pn`, the document's map of numbers by property (§6). */
    public function field(): IndexField
    {
        return new IndexField('pn.'.$this->property->id, IndexField::FLOAT);
    }

    protected function narrow(QueryBuilder $rows, FacetValue $value): void
    {
        if ($value->min !== null) {
            $rows->where(FacetRows::TABLE.'.number', '>=', $value->min);
        }

        if ($value->max !== null) {
            $rows->where(FacetRows::TABLE.'.number', '<=', $value->max);
        }
    }
}
