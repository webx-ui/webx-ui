<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Exchange;

use WebxUi\Catalog\Exchange\ExchangeColumnProvider;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\Localization\Locales;

/**
 * A column of exchange files for every property out of the bin (§3 of the exchange spec), asked
 * each time the columns are listed — a property added a minute ago is a column now. A code the
 * core already uses is the registry's business: it becomes `p_<code>`, and doctor says so.
 */
final class PropertyExchangeColumns implements ExchangeColumnProvider
{
    public function __construct(
        private readonly Properties $properties,
        private readonly ProductValues $values,
        private readonly PropertySets $sets,
        private readonly Locales $locales,
    ) {}

    public function columns(): iterable
    {
        $locale = $this->locales->defaultCode();
        // One page of values for all the columns of one listing — the export's.
        $page = new ExportPage($this->values, $this->sets, $locale);

        foreach ($this->properties->all() as $property) {
            yield new PropertyExchangeColumn($property, $page, $locale);
        }
    }
}
