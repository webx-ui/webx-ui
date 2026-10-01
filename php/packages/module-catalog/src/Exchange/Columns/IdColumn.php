<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange\Columns;

use Illuminate\Support\Collection;
use WebxUi\Catalog\Exchange\ExchangeColumn;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\RowError;

/**
 * The product's id: a key and nothing else. A new product never takes the id a file gives it.
 */
final class IdColumn implements ExchangeColumn
{
    public function key(): string
    {
        return 'id';
    }

    public function label(): string
    {
        return (string) __('webx-catalog::exchange.columns.id');
    }

    public function field(): string
    {
        return '';
    }

    public function localized(): bool
    {
        return false;
    }

    public function export(Collection $products, ?string $locale): array
    {
        $cells = [];

        foreach ($products as $product) {
            $cells[(int) $product->getKey()] = (string) $product->getKey();
        }

        return $cells;
    }

    public function parse(string $cell, ImportContext $context): mixed
    {
        $clean = trim($cell);

        return ctype_digit($clean) ? (int) $clean : throw RowError::because('not-an-id');
    }
}
