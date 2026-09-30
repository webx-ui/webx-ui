<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Tests\Fixtures;

use WebxUi\Catalog\Engine\CatalogEngine;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Engine\CatalogResult;
use WebxUi\Catalog\Engine\SqlEngine;

/**
 * The database engine that asks to be told what changed, as an engine with an index would: the
 * counts are real, and `touchQuery` fills the queue a test can read.
 */
final class QueueingEngine implements CatalogEngine
{
    public function __construct(private readonly SqlEngine $sql) {}

    public function search(CatalogQuery $query): CatalogResult
    {
        return $this->sql->search($query);
    }

    public function needsIndex(): bool
    {
        return true;
    }

    public function prepare(array $fields, bool $rebuild = false): void {}

    public function index(array $documents): void {}

    public function remove(array $ids): void {}
}
