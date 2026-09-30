<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Search;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Catalog\Models\Product;

/**
 * A satellite's share of the search under `SqlEngine` (§4.5 of the properties spec): what else a
 * typed word may match besides the name, the article number and the barcode — the value of a
 * property, say. Its conditions are joined to the core's with `or`; an engine with an index reads
 * the same words from the document instead.
 */
interface SearchContributor
{
    /**
     * Narrow `$query` to the products whose share of the search matches `$text`. Called inside an
     * `or` group of its own, so a plain `where` here is enough.
     *
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, string $text, string $locale): void;
}
