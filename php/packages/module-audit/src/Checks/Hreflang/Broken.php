<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hreflang;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * An alternate that is not a page — an error, a redirect — or a language code search engines do
 * not read (`en-UK`, `jp`). Either way the pair is dropped.
 */
final class Broken extends HreflangCheck
{
    protected const ID = 'hreflang.broken';

    protected const SEVERITY = Severity::ERROR;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $rows = [];

        foreach ($page->hreflang ?? [] as $alternate) {
            $target = $alternate['url'] === $page->url ? $page : $this->target($context, $alternate['url']);

            if (! self::validCode($alternate['lang'])) {
                $rows[] = ['lang' => $alternate['lang'], 'url' => $alternate['url'], 'status' => $target?->status, 'problem' => 'hreflang-bad-code'];
            } elseif ($target !== null && $target->status !== 200) {
                $rows[] = ['lang' => $alternate['lang'], 'url' => $alternate['url'], 'status' => $target->status, 'problem' => 'hreflang-bad-answer'];
            }
        }

        if ($rows !== []) {
            yield $this->on($page, 'hreflang-broken', ['count' => count($rows)], [
                'columns' => [Finding::column('lang'), Finding::column('url', 'url'), Finding::column('status', 'status'), Finding::column('problem', 'word')],
                'rows' => $rows,
            ]);
        }
    }
}
