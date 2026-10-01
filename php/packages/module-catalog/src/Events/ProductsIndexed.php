<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Events;

use WebxUi\Catalog\Engine\Indexer;

/**
 * A batch of products reached the engine (§6.5 of the landings spec): what a satellite that keeps
 * a number about the list — a landing's count of products — recounts after. Fired by
 * {@see Indexer} once per batch, so only where the engine keeps an index; with the database
 * answering every search there is nothing to wait for.
 *
 * The categories are the batch's, with their ancestors — the documents' `categories` — so a
 * listener finds the lists the batch changed without reading the products again.
 */
final class ProductsIndexed
{
    /**
     * @param  list<int>  $products
     * @param  list<int>  $categories
     */
    public function __construct(
        public readonly array $products,
        public readonly array $categories,
    ) {}
}
