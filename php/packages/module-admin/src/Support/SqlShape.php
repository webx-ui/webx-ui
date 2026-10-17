<?php

declare(strict_types=1);

namespace WebxUi\Admin\Support;

/**
 * A statement with its bindings-only differences removed — what a repeated query is grouped by.
 *
 * Two statements share a shape when they differ only in their bindings; `in (?, ?)` and
 * `in (?, ?, ?)` are one shape too, since eager loading asks the same question with a longer
 * list. The query budgets of the tests and the debug bar group by the same thing, so an N+1 one
 * of them names is the N+1 the other one shows.
 */
final class SqlShape
{
    public static function of(string $sql): string
    {
        $collapsed = preg_replace('/\(\s*\?(?:\s*,\s*\?)*\s*\)/', '(?)', $sql) ?? $sql;

        return trim(preg_replace('/\s+/', ' ', $collapsed) ?? $collapsed);
    }
}
