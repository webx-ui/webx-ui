<?php

declare(strict_types=1);

namespace WebxUi\Cart\Contracts;

use WebxUi\Cart\PurchasableLine;

/**
 * Who sells what lies in a line: the catalogue's product, later a variant, a bundle, a gift card,
 * a configuration. Asked for a batch of a type at a time, never per line.
 */
interface Purchasable
{
    /**
     * What these records are and cost right now, in this language.
     *
     * @param  list<int>  $refs  ref ids of one type, no duplicates
     * @return array<int, PurchasableLine|null> ref id → line; null — the record is gone.
     *                                          A ref left out of the answer counts as null.
     */
    public function resolve(array $refs, string $locale): array;
}
