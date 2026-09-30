<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

/**
 * What one chunk of an import did, gathered while its rows go and written into the run once.
 */
final class ChunkResult
{
    public int $rows = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var list<array{row: int, column: string|null, value: string|null, message: string}> */
    public array $errors = [];

    /** @var list<int> the products the rows found or created */
    public array $seen = [];

    public function error(int $row, ?string $column, ?string $value, string $message): void
    {
        $this->errors[] = [
            'row' => $row,
            'column' => $column === null ? null : mb_substr($column, 0, 80),
            'value' => $value === null ? null : mb_substr($value, 0, 500),
            'message' => mb_substr($message, 0, 255),
        ];
    }
}
