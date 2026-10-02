<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

/**
 * An owner of a list with an order of its own (§10.4 of the landings spec): the list opens in it,
 * and its page counts as unsorted — open to the index, its canonical clean. The reader's `?sort=`
 * still beats it.
 *
 * A contract of its own rather than a method of {@see ListingSubject}, so a brand, which has no
 * such order, does not have to say so.
 */
interface HasDefaultSort
{
    /** A key of `webx-catalog.sorts` the storefront offers; null, or one it does not, is the catalogue's default. */
    public function defaultSort(): ?string;
}
