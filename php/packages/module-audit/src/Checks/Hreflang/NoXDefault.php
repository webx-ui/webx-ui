<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hreflang;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * Several languages and no `x-default` — a visitor whose language is none of them gets whichever
 * version the search engine guesses.
 */
final class NoXDefault extends HreflangCheck
{
    protected const ID = 'hreflang.no_x_default';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $codes = array_map(static fn (array $alternate): string => strtolower($alternate['lang']), $page->hreflang ?? []);

        if (in_array('x-default', $codes, true) || count(array_unique($codes)) < 2) {
            return;
        }

        $rows = array_map(static fn (array $alternate): array => ['lang' => $alternate['lang'], 'url' => $alternate['url']], $page->hreflang ?? []);
        $rows[] = ['lang' => 'x-default', 'url' => null];

        yield $this->on($page, 'hreflang-no-x-default', ['count' => count(array_unique($codes))], [
            'columns' => [Finding::column('lang'), Finding::column('url', 'missing')],
            'rows' => $rows,
        ]);
    }
}
