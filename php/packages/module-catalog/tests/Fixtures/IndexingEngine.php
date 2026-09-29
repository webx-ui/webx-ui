<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests\Fixtures;

use RuntimeException;
use WebxUi\Catalog\Engine\CatalogEngine;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Engine\CatalogResult;

/**
 * An engine that keeps an index of its own, the way Manticore does — in memory, and able to fall
 * over on request.
 */
final class IndexingEngine implements CatalogEngine
{
    /** @var array<int, array<string, mixed>> */
    public array $documents = [];

    /** @var list<int> */
    public array $removed = [];

    /** @var list<array{fields: int, rebuild: bool}> */
    public array $prepared = [];

    public int $writes = 0;

    public bool $failing = false;

    public function search(CatalogQuery $query): CatalogResult
    {
        return new CatalogResult(array_keys($this->documents), count($this->documents));
    }

    public function needsIndex(): bool
    {
        return true;
    }

    public function prepare(array $fields, bool $rebuild = false): void
    {
        $this->prepared[] = ['fields' => count($fields), 'rebuild' => $rebuild];

        if ($rebuild) {
            $this->documents = [];
        }
    }

    public function index(array $documents): void
    {
        if ($this->failing) {
            throw new RuntimeException('The engine is down.');
        }

        $this->writes++;

        foreach ($documents as $id => $document) {
            $this->documents[$id] = $document;
        }
    }

    public function remove(array $ids): void
    {
        foreach ($ids as $id) {
            unset($this->documents[$id]);
            $this->removed[] = $id;
        }
    }
}
