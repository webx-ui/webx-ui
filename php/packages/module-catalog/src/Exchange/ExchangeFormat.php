<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

/**
 * A kind of file the exchange reads and writes (§2 of the exchange spec). The core registers
 * `csv` and `xlsx`; YML, CommerceML and Google Merchant come as packages of their own through
 * the same registry.
 *
 * Both directions stream: 85 thousand rows are never held at once.
 */
interface ExchangeFormat
{
    /** `[a-z0-9]`, what a profile and a run store. */
    public function key(): string;

    /**
     * The file extensions it is recognised by, lowercase, without the dot.
     *
     * @return list<string>
     */
    public function extensions(): array;

    /**
     * How this file is read: what was given, and what was guessed for what was not — the
     * encoding and the separator of a CSV. What `inspect` shows and a run keeps.
     *
     * @param  array<string, mixed>  $given
     * @return array<string, mixed>
     */
    public function options(string $path, array $given = []): array;

    /**
     * The rows of the file, the header included, as text: row number (from 1, as a spreadsheet
     * counts) → cells. Empty rows are skipped but counted.
     *
     * @param  array<string, mixed>  $options  what `options()` answered
     * @return iterable<int, list<string>>
     */
    public function read(string $path, array $options): iterable;

    /**
     * Write the rows into a new file, the header first. Every cell is text: an article number
     * `0012` stays `0012`, and a description that begins with `=` is not a formula.
     *
     * @param  iterable<list<string>>  $rows
     * @return int how many rows were written
     */
    public function write(string $path, iterable $rows): int;
}
