<?php

declare(strict_types=1);

namespace WebxUi\Media\Usage;

/**
 * One row that points at one file: the table, the column, and the row's id when it has one.
 */
final class Place
{
    public function __construct(
        public readonly int $fileId,
        public readonly string $table,
        public readonly string $column,
        public readonly int|string|null $id = null,
    ) {}

    /**
     * @return array{table: string, column: string, id: int|string|null}
     */
    public function toArray(): array
    {
        return ['table' => $this->table, 'column' => $this->column, 'id' => $this->id];
    }
}
