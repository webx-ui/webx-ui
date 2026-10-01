<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange\Formats;

use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use WebxUi\Catalog\Exchange\ExchangeFormat;

/**
 * XLSX, the first sheet of it. The old binary `.xls` is not read (decision 2 of the exchange
 * spec): whoever has one saves it again.
 */
final class XlsxFormat implements ExchangeFormat
{
    use SpreadsheetCells;

    public function key(): string
    {
        return 'xlsx';
    }

    public function extensions(): array
    {
        return ['xlsx'];
    }

    public function options(string $path, array $given = []): array
    {
        return [];
    }

    public function read(string $path, array $options): iterable
    {
        $settings = new ReaderOptions;
        $settings->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return self::rowsOf(new Reader($settings), $path);
    }

    public function write(string $path, iterable $rows): int
    {
        return self::writeRows(new Writer, $path, $rows);
    }
}
