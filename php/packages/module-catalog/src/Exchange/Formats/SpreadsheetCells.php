<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange\Formats;

use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\BooleanCell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\RowIteratorInterface;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\SheetIteratorInterface;
use OpenSpout\Writer\WriterInterface;

/**
 * What the two formats share: every cell read becomes text the columns parse, and every cell
 * written is text. A spreadsheet that turned an article number into a number, or a price into
 * `1.5E+3`, gets it back as the digits a person typed.
 */
trait SpreadsheetCells
{
    /**
     * The rows of the first sheet, numbered as a spreadsheet numbers them; an empty row is skipped
     * and still counted, so that "row 12" in an error is the row 12 the editor opens.
     *
     * @param  ReaderInterface<covariant SheetIteratorInterface<covariant SheetInterface<covariant RowIteratorInterface>>>  $reader
     * @return iterable<int, list<string>>
     */
    private static function rowsOf(ReaderInterface $reader, string $path): iterable
    {
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $number = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $number++;
                    $cells = array_map(self::text(...), $row->getCells());

                    if (implode('', $cells) === '') {
                        continue;
                    }

                    yield $number => $cells;
                }

                // One sheet is the file: the rest are notes somebody kept beside the data.
                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  iterable<list<string>>  $rows
     */
    private static function writeRows(WriterInterface $writer, string $path, iterable $rows): int
    {
        $writer->openToFile($path);
        $count = 0;

        try {
            foreach ($rows as $cells) {
                // A string cell rather than `Cell::fromValue()`: that one reads `=…` as a formula.
                $writer->addRow(new Row(array_map(static fn (string $cell): Cell => new StringCell($cell, null), $cells)));
                $count++;
            }
        } finally {
            $writer->close();
        }

        return $count;
    }

    private static function text(Cell $cell): string
    {
        $value = $cell instanceof FormulaCell ? ($cell->getComputedValue() ?? $cell->getValue()) : $cell->getValue();

        return match (true) {
            $cell instanceof ErrorCell, $value === null => '',
            $cell instanceof BooleanCell, is_bool($value) => $value ? '1' : '0',
            $cell instanceof DateTimeCell, $value instanceof DateTimeInterface => self::date($value),
            is_int($value) => (string) $value,
            is_float($value) => self::number($value),
            is_string($value) => $value,
            default => '',
        };
    }

    private static function number(float $value): string
    {
        if (floor($value) === $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
    }

    private static function date(mixed $value): string
    {
        if (! $value instanceof DateTimeInterface) {
            return '';
        }

        return $value->format('H:i:s') === '00:00:00' ? $value->format('Y-m-d') : $value->format('Y-m-d H:i:s');
    }
}
