<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Runs\AuditPage;

/**
 * The page's headings in order, as the parser kept them. Empty on a run crawled before it did,
 * so the checks built on it stay quiet there instead of reporting a page with no headings.
 */
final class Outline
{
    /** @return list<array{0: int, 1: string}> */
    public static function of(AuditPage $page): array
    {
        $kept = $page->fact('outline');

        if (! is_array($kept)) {
            return [];
        }

        $out = [];

        foreach ($kept as $heading) {
            if (is_array($heading) && isset($heading[0], $heading[1])) {
                $out[] = [(int) $heading[0], (string) $heading[1]];
            }
        }

        return $out;
    }
}
