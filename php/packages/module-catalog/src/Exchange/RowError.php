<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use RuntimeException;

/**
 * What a codec says about a cell it cannot read, and what the import writes into the run's list
 * of errors: which column, what was in it, why. A column of null is the row as a whole — a
 * product in the bin, a key nobody has.
 */
final class RowError extends RuntimeException
{
    public function __construct(
        string $reason,
        public readonly ?string $column = null,
        public readonly ?string $value = null,
    ) {
        parent::__construct($reason);
    }

    /**
     * The column's own words: the importer puts the column and the cell on it afterwards.
     *
     * @param  array<string, string|int>  $replace
     */
    public static function because(string $key, array $replace = []): self
    {
        return new self((string) __('webx-catalog::exchange.errors.'.$key, $replace));
    }

    public function at(string $column, string $value): self
    {
        return new self($this->getMessage(), $this->column ?? $column, $this->value ?? $value);
    }
}
