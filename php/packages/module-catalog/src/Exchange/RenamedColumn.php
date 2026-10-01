<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Illuminate\Support\Collection;

/**
 * A provider's column whose code was the core's already: the same column under `p_<code>`.
 */
final class RenamedColumn implements DescribesCell, ExchangeColumn
{
    public function __construct(
        private readonly ExchangeColumn $column,
        private readonly string $key,
    ) {}

    /** The column under its own code — what the importer asks whether it fills an entry. */
    public function column(): ExchangeColumn
    {
        return $this->column;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->column->label();
    }

    public function field(): string
    {
        return $this->column->field();
    }

    public function localized(): bool
    {
        return $this->column->localized();
    }

    public function cellFormat(): string
    {
        return $this->column instanceof DescribesCell ? $this->column->cellFormat() : '';
    }

    public function export(Collection $products, ?string $locale): array
    {
        return $this->column->export($products, $locale);
    }

    public function parse(string $cell, ImportContext $context): mixed
    {
        return $this->column->parse($cell, $context);
    }
}
