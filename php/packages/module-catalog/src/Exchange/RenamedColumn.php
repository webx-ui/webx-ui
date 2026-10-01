<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Illuminate\Support\Collection;

/**
 * A provider's column whose code was the core's already: the same column under `p_<code>`.
 */
final class RenamedColumn implements ExchangeColumn
{
    public function __construct(
        private readonly ExchangeColumn $column,
        private readonly string $key,
    ) {}

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

    public function export(Collection $products, ?string $locale): array
    {
        return $this->column->export($products, $locale);
    }

    public function parse(string $cell, ImportContext $context): mixed
    {
        return $this->column->parse($cell, $context);
    }
}
