<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange\Columns;

use Illuminate\Support\Collection;
use WebxUi\Catalog\Exchange\DescribesCell;
use WebxUi\Catalog\Exchange\ExchangeColumn;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductBarcode;

/**
 * The additional barcodes, through `;`. They only describe a product: a row is never matched by
 * them (decision 17 of the feeds spec) — the `barcode` key is the main barcode alone.
 */
final class BarcodesColumn implements DescribesCell, ExchangeColumn
{
    public function key(): string
    {
        return 'barcodes';
    }

    public function label(): string
    {
        return (string) __('webx-catalog::product.barcodes');
    }

    public function field(): string
    {
        return 'barcodes';
    }

    public function localized(): bool
    {
        return false;
    }

    public function cellFormat(): string
    {
        return 'Additional barcodes through ";". They replace the list; an empty cell leaves it, with empty_clears it erases it. '
            .'A row is never found by them: the barcode key is the main barcode.';
    }

    public function export(Collection $products, ?string $locale): array
    {
        $ids = $products->map(static fn (Product $product): int => (int) $product->id)->all();
        $codes = [];

        foreach (ProductBarcode::query()->whereIn('product_id', $ids)->orderBy('position')->orderBy('id')->get() as $row) {
            $codes[$row->product_id][] = $row->barcode;
        }

        $cells = [];

        foreach ($ids as $id) {
            $cells[$id] = implode(';', $codes[$id] ?? []);
        }

        return $cells;
    }

    public function parse(string $cell, ImportContext $context): mixed
    {
        $codes = array_filter(array_map(trim(...), explode(';', $cell)), static fn (string $code): bool => $code !== '');

        return array_values(array_unique($codes));
    }
}
