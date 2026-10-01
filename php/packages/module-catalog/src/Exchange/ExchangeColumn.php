<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Illuminate\Support\Collection;
use WebxUi\Catalog\Models\Product;

/**
 * One column of an exchange file (§3 of the exchange spec): a codec between a cell and a field of
 * the product form, not a way into the database.
 *
 * The import builds from a row the same array the editor sends and hands it to
 * `ProductForm::save()`, so a satellite never teaches the exchange a second time how its data is
 * written: its column only says how a cell reads as the value of its field, and how a page of
 * products reads back as cells.
 */
interface ExchangeColumn
{
    /** The header code: `[a-z0-9_]`, what a file carries in its first row. */
    public function key(): string;

    /** Words for the mapping screen. */
    public function label(): string;

    /**
     * The form field it fills: `name`, `price`, `stock.status`, `properties.cvet`. Empty for a
     * column that only finds the product (`id`).
     */
    public function field(): string;

    /** Whether `key@<locale>` columns exist for it. */
    public function localized(): bool;

    /**
     * Cells for a page of products, for the export — product id → text. Locale is null for the
     * default language.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, string>
     */
    public function export(Collection $products, ?string $locale): array;

    /**
     * The cell as the form wants it. Throws RowError on a value it cannot read; with
     * `$context->createMissing` it may create a reference-book value and return its id.
     *
     * @throws RowError
     */
    public function parse(string $cell, ImportContext $context): mixed;
}
