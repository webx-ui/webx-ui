<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use WebxUi\Catalog\Models\Product;

/**
 * One row of the file on its way in: its number as a spreadsheet shows it, its cells by column
 * code and language, and the product its key found — null for one to create.
 */
final class ImportRow
{
    public ?Product $product = null;

    /**
     * @param  array<string, array<string, string>>  $cells  column key → locale (`''` — the default) → cell
     */
    public function __construct(
        public readonly int $number,
        public readonly array $cells,
    ) {}

    /** The cell of a column in the default language, `''` for none. */
    public function cell(string $key): string
    {
        return $this->cells[$key][''] ?? '';
    }
}
