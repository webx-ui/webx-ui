<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Variables;

use Illuminate\Support\Collection;
use WebxUi\Catalog\Exchange\ExchangeColumn;
use WebxUi\Localization\Locales;
use WebxUi\Templates\Variable;

/** A column of the exchange as a variable (decision 5): its cells, in the language of the feed. */
final readonly class ColumnVariable implements ProductVariable
{
    public function __construct(private ExchangeColumn $column, private Locales $locales) {}

    public function describe(): Variable
    {
        return new Variable($this->column->key(), $this->column->label());
    }

    public function values(Collection $products, string $locale): array
    {
        // What the exporter hands a column: null is the default language.
        return $this->column->export($products, $locale === $this->locales->defaultCode() ? null : $locale);
    }
}
