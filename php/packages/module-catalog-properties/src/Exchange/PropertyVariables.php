<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Exchange;

use WebxUi\Catalog\Variables\ProductVariableProvider;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\Localization\Locales;

/**
 * A variable `property.<code>` for every property out of the bin (§4.2 of the feeds spec), the code
 * written as its column's: `-` as `_`. Asked each time the variables are listed; the ones of one
 * listing read a page of values together.
 */
final class PropertyVariables implements ProductVariableProvider
{
    public function __construct(
        private readonly Properties $properties,
        private readonly ProductValues $values,
        private readonly PropertySets $sets,
        private readonly Locales $locales,
    ) {}

    public function variables(): iterable
    {
        $locale = $this->locales->defaultCode();
        $page = new ExportPage($this->values, $this->sets, $locale);

        foreach ($this->properties->all() as $property) {
            yield new PropertyVariable($property, $page, $locale);
        }
    }
}
