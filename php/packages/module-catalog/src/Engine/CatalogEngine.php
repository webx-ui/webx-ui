<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Engine;

use WebxUi\Catalog\Facets\IndexField;

/**
 * Who answers the catalogue's questions (§8, §4.3 of the architecture): the storefront's list,
 * its filter and its counts, and the panel's list — the same engine for both (decision 14).
 *
 * `SqlEngine` is the database and keeps no index; an engine that keeps one says so with
 * {@see needsIndex()}, and only then does anybody write to the queue (§8.3).
 */
interface CatalogEngine
{
    public function search(CatalogQuery $query): CatalogResult;

    /** Whether saves have to be queued for this engine at all. */
    public function needsIndex(): bool;

    /**
     * The shape of the index, from every contributor's fields; `$rebuild` starts it from empty.
     *
     * @param  list<IndexField>  $fields
     */
    public function prepare(array $fields, bool $rebuild = false): void;

    /**
     * Write documents. Writing one twice is harmless: a retried batch replaces what it wrote.
     *
     * @param  array<int, array<string, mixed>>  $documents  product id → document
     */
    public function index(array $documents): void;

    /**
     * @param  list<int>  $ids
     */
    public function remove(array $ids): void;
}
