<?php

declare(strict_types=1);

namespace WebxUi\Seo\Links;

use WebxUi\Seo\Spreadsheets\Sheet;

/**
 * The flat file of an interlinking brief (§18.4): one line per link — donor, acceptor, anchor,
 * and an optional heading.
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

    /**
     * @return array<int, array<string, string>>
     */
    public static function read(string $path, string $extension): array
    {
        return Sheet::read($path, $extension, self::COLUMNS, self::HEADERS, ['donor', 'acceptor', 'anchor']);
    }

    /**
     * @param  iterable<list<string>>  $rows  with the header first
     */
    public static function write(string $path, string $extension, iterable $rows): void
    {
        Sheet::write($path, $extension, $rows);
    }
}
