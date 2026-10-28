<?php

declare(strict_types=1);

namespace WebxUi\Cart\Contracts;

use WebxUi\Cart\CartRef;
use WebxUi\Cart\PurchasableLine;

/** A provider whose price and label depend on the options of a line, not only on its record. */
interface ResolvesWithOptions
{
    /**
     * @param  list<CartRef>  $lines  lines of one type, each with its own options
     * @return array<string, PurchasableLine|null> CartRef::$key → line; null — gone.
     *                                             A key left out counts as null.
     */
    public function resolveLines(array $lines, string $locale): array;
}
