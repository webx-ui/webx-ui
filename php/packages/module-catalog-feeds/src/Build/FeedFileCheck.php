<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Build;

use WebxUi\CatalogFeeds\Models\Feed;
use XMLReader;

/**
 * The finished file, before anybody is given it (§7.2 of the feeds spec): a marketplace is better
 * off with yesterday's file than with a broken one. Everything goes through the file as a stream.
 */
final class FeedFileCheck
{
    /**
     * @throws FeedFailure
     */
    public function file(Feed $feed, string $path, string $head, string $foot, int $written): void
    {
        match ($feed->format) {
            'xml' => $this->xml($path),
            'json' => $this->json($feed, $head, $foot, $written),
            'csv' => $this->csv($path, self::delimiter($feed)),
            default => null,
        };
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
            $error = libxml_get_last_error();

            if ($error !== false) {
                throw FeedFailure::of('file-xml', ['line' => $error->line, 'reason' => trim($error->message)]);
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
     */
    private function json(Feed $feed, string $head, string $foot, int $written): void
    {
        if (trim($head.$foot) === '' && trim($feed->separator) === '') {
            return;
        }

        $sample = $head.implode($feed->separator, array_fill(0, min($written, 2), 'null')).$foot;
        json_decode($sample);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw FeedFailure::of('file-json', ['reason' => json_last_error_msg()]);
        }
    }

    private function csv(string $path, string $delimiter): void
    {
        $file = fopen($path, 'rb');

        if ($file === false) {
            throw FeedFailure::of('file-csv', ['line' => 0, 'found' => 0, 'expected' => 0]);
        }

        try {
            $expected = null;
            $line = 0;

            while (($row = fgetcsv($file, null, $delimiter, '"', '')) !== false) {
                $line++;

                // A line with nothing on it reads as one empty field; the last one always does.
                if ($row === [null]) {
                    continue;
                }

                $expected ??= count($row);

                if (count($row) !== $expected) {
                    throw FeedFailure::of('file-csv', ['line' => $line, 'found' => count($row), 'expected' => $expected]);
                }
            }
        } finally {
            fclose($file);
        }
    }
}
