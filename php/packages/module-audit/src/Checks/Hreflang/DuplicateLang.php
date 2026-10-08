<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hreflang;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * One language code for two different addresses: search engines cannot tell which is the
 * version for that language and may ignore both.
 */
final class DuplicateLang extends HreflangCheck
{
    protected const ID = 'hreflang.duplicate_lang';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $byLang = [];

        foreach ($page->hreflang ?? [] as $alternate) {
            $byLang[strtolower($alternate['lang'])][$alternate['url']] = true;
        }

        $rows = [];

        foreach ($byLang as $lang => $urls) {
            if (count($urls) > 1) {
                foreach (array_keys($urls) as $url) {
                    $rows[] = ['lang' => $lang, 'url' => $url];
                }
            }
        }

        if ($rows !== []) {
            yield $this->on($page, 'hreflang-duplicate-lang', ['langs' => implode(', ', array_unique(array_column($rows, 'lang')))], [
                'columns' => [Finding::column('lang'), Finding::column('url', 'url')],
                'rows' => $rows,
            ]);
        }
    }
}
