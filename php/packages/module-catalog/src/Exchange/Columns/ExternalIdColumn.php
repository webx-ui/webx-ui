<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange\Columns;

use Illuminate\Support\Collection;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\RowError;
use WebxUi\Catalog\Exchange\WritesProduct;
use WebxUi\Catalog\Models\Product;

/**
 * The id a product has in somebody else's system — an accounting program, a supplier's price
 * list. Not a field of the editor, so it is written beside the form's save rather than through
 * it; and not in the journal, which skips it anyway.
 */
final class ExternalIdColumn implements WritesProduct
{
    public function key(): string
    {
        return 'external_id';
    }

    public function label(): string
    {
        return (string) __('webx-catalog::exchange.columns.external_id');
    }

    public function field(): string
    {
        return 'external_id';
    }

    public function localized(): bool
    {
        return false;
    }

    public function export(Collection $products, ?string $locale): array
    {
        $cells = [];

        foreach ($products as $product) {
            /** @var Product $product */
            $cells[(int) $product->id] = (string) $product->external_id;
        }

        return $cells;
    }

    public function parse(string $cell, ImportContext $context): mixed
    {
        $clean = trim($cell);

        return mb_strlen($clean) <= 64 ? $clean : throw RowError::because('too-long', ['max' => 64]);
    }

    public function write(Product $product, mixed $value, ImportContext $context): void
    {
        $value = is_string($value) && $value !== '' ? $value : null;

        if ($product->external_id === $value) {
            return;
        }

        $holder = Product::withTrashed()->where('external_id', $value)->whereKeyNot($product->id)->value('id');

        if ($value !== null && $holder !== null) {
            throw RowError::because('external-id-taken', ['id' => $holder]);
        }

        $product->forceFill(['external_id' => $value])->saveQuietly();
    }
}
