<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Sorts;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Catalog\Models\Product;

/**
 * An order the catalogue can be read in (§7.2): the core's default, price both ways, name, new
 * and popular; a satellite's "in stock first".
 *
 * Two halves, one per kind of engine: {@see applySql()} for the database, {@see indexOrder()} for
 * an engine that keeps an index of its own. The key is what `?sort=` carries, so it is
 * `[a-z0-9_]` and never changes once a site has links to it.
 */
interface Sort
{
    public function key(): string;

    public function label(): string;

    /**
     * Put the order on a query of products. The engine adds the id last, so equal rows come in
     * one order on every page rather than in whatever order the database felt like.
     *
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, string $locale): void;

    /**
     * The same order in terms of the index's fields, first step first.
     *
     * @return array<string, 'asc'|'desc'>
     */
    public function indexOrder(string $locale): array;
}
