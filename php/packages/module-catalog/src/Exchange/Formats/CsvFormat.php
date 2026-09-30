<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange\Formats;

use OpenSpout\Reader\CSV\Options as ReaderOptions;
use OpenSpout\Reader\CSV\Reader;
use OpenSpout\Writer\CSV\Options as WriterOptions;
use OpenSpout\Writer\CSV\Writer;
use WebxUi\Catalog\Exchange\ExchangeFormat;

/**
 * CSV (§4.1 of the exchange spec). What a CSV does not say about itself is guessed: the encoding
 * by its BOM, else UTF-8 when the bytes are valid UTF-8, else `exchange.csv_fallback_encoding`
 * — what Excel saves in a Western locale; the separator by the header, the most frequent of `,`
 * `;` and a tab outside quotes — `;` is what Excel writes where the comma is the decimal point. A
 * profile overrides either. Written as UTF-8 with a BOM, which Excel needs to read it as UTF-8.
 */
final class CsvFormat implements ExchangeFormat
{
    use SpreadsheetCells;

    /** How much of the file the guesses read. */
    private const SAMPLE = 65536;

    private const DELIMITERS = [',', ';', "\t"];

    private const BOMS = [
        'UTF-8' => "\xEF\xBB\xBF",
        'UTF-16LE' => "\xFF\xFE",
        'UTF-16BE' => "\xFE\xFF",
    ];

    public function key(): string
    {
        return 'csv';
    }

    public function extensions(): array
    {
        return ['csv', 'txt'];
    }

    public function options(string $path, array $given = []): array
    {
        $sample = (string) @file_get_contents($path, false, null, 0, self::SAMPLE);

        $encoding = is_string($given['encoding'] ?? null) && $given['encoding'] !== '' ? $given['encoding'] : self::encoding($sample);
        $delimiter = is_string($given['delimiter'] ?? null) && in_array($given['delimiter'], self::DELIMITERS, true)
            ? $given['delimiter']
            : self::delimiter(self::firstLine($sample, $encoding));

        return ['encoding' => $encoding, 'delimiter' => $delimiter];
    }

    public function read(string $path, array $options): iterable
    {
        $settings = new ReaderOptions;
        $settings->ENCODING = is_string($options['encoding'] ?? null) ? $options['encoding'] : 'UTF-8';
        $settings->FIELD_DELIMITER = is_string($options['delimiter'] ?? null) ? $options['delimiter'] : ',';
        // Kept so that the numbering is the spreadsheet's; the trait skips them.
        $settings->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return self::rowsOf(new Reader($settings), $path);
    }

    public function write(string $path, iterable $rows): int
    {
        $settings = new WriterOptions;
        $settings->SHOULD_ADD_BOM = true;

        return self::writeRows(new Writer($settings), $path, $rows);
    }

    private static function encoding(string $sample): string
    {
        foreach (self::BOMS as $encoding => $bom) {
            if (str_starts_with($sample, $bom)) {
                return $encoding;
            }
        }

        // A sample cut in the middle of a letter is not a reason to think the file is not UTF-8.
        $whole = strlen($sample) < self::SAMPLE ? $sample : substr($sample, 0, max(0, (int) strrpos($sample, "\n")));

        if (mb_check_encoding($whole, 'UTF-8')) {
            return 'UTF-8';
        }

        $fallback = config('webx-catalog.exchange.csv_fallback_encoding');

        return is_string($fallback) && $fallback !== '' ? $fallback : 'Windows-1252';
    }

    private static function firstLine(string $sample, string $encoding): string
    {
        if (isset(self::BOMS[$encoding]) && str_starts_with($sample, self::BOMS[$encoding])) {
            $sample = substr($sample, strlen(self::BOMS[$encoding]));
        }

        if ($encoding !== 'UTF-8') {
            $sample = (string) @mb_convert_encoding($sample, 'UTF-8', $encoding);
        }

        $end = strcspn($sample, "\r\n");

        return substr($sample, 0, $end);
    }

    /** The separator that occurs most often outside quotes; a comma when the header says nothing. */
    private static function delimiter(string $line): string
    {
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
}
