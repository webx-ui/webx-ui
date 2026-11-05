<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use Seld\JsonLint\JsonParser;
use WebxUi\CatalogFeeds\Models\Feed;
use XMLReader;

/**
 * The finished file, before anybody is given it (§7.2 of the feeds spec): a marketplace is better
 * off with yesterday's file than with a broken one. Everything goes through the file as a stream.
 *
 * A csv is the slow one, minutes on a file near `max_bytes`, and the one that can stop at a row:
 * it reads until the job's time is up and says where it got to. XML is read at once, a parser
 * cannot be put down, and that is seconds on the same file.
 *
 * @phpstan-type Reached array{offset?: int, line?: int, expected?: int|null}
 */
final class FeedFileCheck
{
    /**
     * @param  Reached  $from  where the last job stopped
     * @return Reached|null where this one did, null for a file read to its end
     *
     * @throws FeedFailure
     */
    public function file(Feed $feed, string $path, array $from, float $until): ?array
    {
        if ($feed->format === 'csv') {
            return $this->csv($path, self::delimiter($feed), $from, $until);
        }

        if ($feed->format === 'xml') {
            $this->xml($path);
        }

        return null;
    }

    /**
     * A product's own text, as it is written: a whole JSON file would not fit in memory, so each
     * product is read by itself and the frame around them by {@see json()}.
     *
     * @throws FeedFailure
     */
    public function jsonItem(string $text, int $product): void
    {
        json_decode($text);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw FeedFailure::of('file-json-item', ['product' => $product, 'reason' => json_last_error_msg()]);
        }
    }

    /**
     * JSON read for a person: `json_decode` says what is wrong but not where, which a sample of a
     * few products can afford and a whole file cannot.
     *
     * @throws FeedFailure
     */
    public function jsonText(string $text): void
    {
        $error = (new JsonParser)->lint($text);

        if ($error === null) {
            return;
        }

        $at = $error->getDetails()['loc'] ?? [];
        $words = explode("\n", trim($error->getMessage()));

        throw FeedFailure::of('sample-json', [
            // `loc` is the last token it took, not the one it choked on: the mistake starts after it.
            'line' => (int) ($at['last_line'] ?? 1),
            // In bytes and from zero, unlike the line and unlike the column of libxml.
            'column' => (int) ($at['last_column'] ?? 0) + 1,
            'reason' => (string) end($words),
        ]);
    }

    public static function delimiter(Feed $feed): string
    {
        $delimiter = $feed->options['delimiter'] ?? ',';

        return is_string($delimiter) && strlen($delimiter) === 1 ? $delimiter : ',';
    }

    private function xml(string $path): void
    {
        $internal = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            // No entities and no network: a template may hold anything a person typed.
            $reader = XMLReader::open($path, 'UTF-8', LIBXML_NONET);

            if (! $reader instanceof XMLReader) {
                throw FeedFailure::of('file-xml', ['line' => 0, 'reason' => 'unreadable']);
            }

            $seen = false;

            while ($reader->read()) {
                $seen = true;
            }

            $reader->close();
            // The first one: what follows a broken tag is mostly the parser losing its way.
            $error = libxml_get_errors()[0] ?? null;

            if ($error !== null) {
                // libxml stands just past what it read, which may already be the next part of a
                // template, and it counts characters, not bytes.
                throw FeedFailure::of('file-xml', ['line' => $error->line, 'column' => max(1, $error->column - 1), 'reason' => trim($error->message)]);
            }

            if (! $seen) {
                throw FeedFailure::of('file-xml', ['line' => 0, 'reason' => 'empty']);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($internal);
        }
    }

    /**
     * The frame around the products, on a sample: `null` stands in a place for any value, and two
     * of them show the separator as well. A feed of lines has no frame to read.
     *
     * @throws FeedFailure
     */
    public function frame(Feed $feed, string $head, string $foot, int $written): void
    {
        if ($feed->format !== 'json' || (trim($head.$foot) === '' && trim($feed->separator) === '')) {
            return;
        }

        $sample = $head.implode($feed->separator, array_fill(0, min($written, 2), 'null')).$foot;
        json_decode($sample);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw FeedFailure::of('file-json', ['reason' => json_last_error_msg()]);
        }
    }

    /**
     * @param  Reached  $from
     * @return Reached|null
     */
    private function csv(string $path, string $delimiter, array $from, float $until): ?array
    {
        $file = fopen($path, 'rb');

        if ($file === false) {
            throw FeedFailure::of('file-csv', ['line' => 0, 'found' => 0, 'expected' => 0]);
        }

        try {
            $expected = $from['expected'] ?? null;
            $line = $from['line'] ?? 0;
            fseek($file, $from['offset'] ?? 0);

            while (($row = fgetcsv($file, null, $delimiter, '"', '')) !== false) {
                $at = $line + 1;
                // A quoted value may hold line breaks, and a person counts lines of the file.
                $line = $at + substr_count(implode('', $row), "\n");

                // A line with nothing on it reads as one empty field; the last one always does.
                if ($row === [null]) {
                    continue;
                }

                $expected ??= count($row);

                if (count($row) !== $expected) {
                    throw FeedFailure::of('file-csv', ['line' => $at, 'found' => count($row), 'expected' => $expected]);
                }

                if (microtime(true) >= $until) {
                    return ['offset' => (int) ftell($file), 'line' => $line, 'expected' => $expected];
                }
            }

            return null;
        } finally {
            fclose($file);
        }
    }
}
