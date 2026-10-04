<?php

declare(strict_types=1);

namespace WebxUi\Seo\Faq;

use WebxUi\Seo\Spreadsheets\Sheet;

/**
 * The flat file of page FAQs (§18.5): one line per question — the address, the question and the
 * answer, HTML or plain text.
 */
final class FaqSpreadsheet
{
    public const COLUMNS = ['address', 'question', 'answer'];

    /** The header a brief is written with, in the languages briefs come in, lower case. */
    private const HEADERS = [
        'address' => ['address', 'url', 'page', 'адрес', 'страница'],
        'question' => ['question', 'вопрос'],
        'answer' => ['answer', 'ответ'],
    ];

    /**
     * @return array<int, array<string, string>>
     */
    public static function read(string $path, string $extension): array
    {
        return Sheet::read($path, $extension, self::COLUMNS, self::HEADERS, self::COLUMNS);
    }

    /**
     * @param  iterable<list<string>>  $rows  with the header first
     */
    public static function write(string $path, string $extension, iterable $rows): void
    {
        Sheet::write($path, $extension, $rows);
    }
}
