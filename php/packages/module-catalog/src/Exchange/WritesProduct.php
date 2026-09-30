<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use WebxUi\Catalog\Models\Product;

/**
 * A column whose value is not a field of the form: the outside id, which the editor never shows,
 * and the gallery, which the editor fills with files rather than with a value. Written after the
 * form's save, inside the same savepoint — a row that fails takes it back too.
 *
 * Its `field()` is still what the journal and the errors call it; the registry does not look for
 * it on the screen.
 */
interface WritesProduct extends ExchangeColumn
{
    /**
     * @param  mixed  $value  what `parse()` answered, or null for a cell emptied with `empty_clears`
     *
     * @throws RowError
     */
    public function write(Product $product, mixed $value, ImportContext $context): void;
}
