<?php

declare(strict_types=1);

namespace WebxUi\Seo\Links;

use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvReaderOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Options as CsvWriterOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * The flat file of a brief (§18.4): one line per link — donor, acceptor, anchor, and an optional
 * heading. Read and written the same way, so a file that came out of the export goes back in.
 *
 * Every cell is text: a spreadsheet that turned an address into something else gives it back as
 * the characters a person typed, and nothing written starting with `=` turns into a formula.
 */
final class LinkSpreadsheet
{
    public const COLUMNS = ['donor', 'acceptor', 'anchor', 'heading'];

    /** The header a brief is written with, in the languages briefs come in, lower case. */
    private const HEADERS = [
        'donor' => ['donor', 'донор', 'донор (откуда)'],
        'acceptor' => ['acceptor', 'акцептор', 'акцептор (куда)'],
        'anchor' => ['anchor', 'анкор', 'текст ссылки'],
        'heading' => ['heading', 'заголовок', 'заголовок блока'],
    ];

    /** The CSV separators guessed from the first line, the most frequent outside quotes. */
    private const DELIMITERS = [',', ';', "\t"];

    /**
     * The lines of the first sheet as rows keyed by column, numbered as the spreadsheet numbers
     * them. A first line that names the columns is the header; otherwise the columns are taken in
     * the order above and the first line is data.
     *
     * @return array<int, array{donor: string, acceptor: string, anchor: string, heading: string}>
     */
    public static function read(string $path, string $extension): array
    {
        $lines = self::lines($path, strtolower($extension));
        $map = null;
        $rows = [];

        foreach ($lines as $number => $cells) {
            if ($map === null) {
                $map = self::header($cells);

                if ($map !== null) {
                    continue;
                }

                $map = array_flip(self::COLUMNS);
            }

            $row = [];

            foreach (self::COLUMNS as $column) {
                $index = $map[$column] ?? null;
                $row[$column] = $index === null ? '' : trim($cells[$index] ?? '');
            }

            $rows[$number] = $row;
        }

        return $rows;
    }

    /**
     * @param  iterable<list<string>>  $rows  with the header first
     */
    public static function write(string $path, string $extension, iterable $rows): void
    {
        if (strtolower($extension) === 'xlsx') {
            $writer = new XlsxWriter;
        } else {
            $options = new CsvWriterOptions;
            // Excel reads a CSV without the mark in the locale's own encoding.
            $options->SHOULD_ADD_BOM = true;
            $writer = new CsvWriter($options);
        }

        $writer->openToFile($path);

        try {
            foreach ($rows as $cells) {
                $writer->addRow(new Row(array_map(static fn (string $cell): Cell => new StringCell($cell, null), $cells)));
            }
        } finally {
            $writer->close();
        }
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, int>|null
     */
    private static function header(array $cells): ?array
    {
        $map = [];

        foreach ($cells as $index => $cell) {
            $name = mb_strtolower(trim($cell));

            foreach (self::HEADERS as $column => $names) {
                if (in_array($name, $names, true) && ! isset($map[$column])) {
                    $map[$column] = $index;
                }
            }
        }

        return isset($map['donor'], $map['acceptor'], $map['anchor']) ? $map : null;
    }

    /**
     * @return array<int, list<string>>
     */
    private static function lines(string $path, string $extension): array
    {
        if ($extension === 'xlsx') {
            $reader = new XlsxReader;
        } else {
            $options = new CsvReaderOptions;
            $options->FIELD_DELIMITER = self::delimiter($path);
            $reader = new CsvReader($options);
        }

        $reader->open($path);
        $lines = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $number = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $number++;
                    $cells = array_map(self::text(...), $row->getCells());

                    if (implode('', $cells) !== '') {
                        $lines[$number] = $cells;
                    }
                }

                // One sheet is the file: the rest are notes somebody kept beside it.
                break;
            }
        } finally {
            $reader->close();
        }

        return $lines;
    }

    private static function delimiter(string $path): string
    {
        $line = (string) @file_get_contents($path, false, null, 0, 8192);
        $line = substr($line, 0, strcspn($line, "\r\n"));
        $counts = array_fill_keys(self::DELIMITERS, 0);
        $quoted = false;

        foreach (str_split($line) as $char) {
            if ($char === '"') {
                $quoted = ! $quoted;
            } elseif (! $quoted && isset($counts[$char])) {
                $counts[$char]++;
            }
        }

        arsort($counts);
        $best = (string) array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    private static function text(Cell $cell): string
    {
        $value = $cell instanceof FormulaCell ? ($cell->getComputedValue() ?? $cell->getValue()) : $cell->getValue();

        return match (true) {
            is_string($value) => $value,
            is_int($value) => (string) $value,
            is_float($value) => rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.'),
            is_bool($value) => $value ? '1' : '0',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            default => '',
        };
    }
}
