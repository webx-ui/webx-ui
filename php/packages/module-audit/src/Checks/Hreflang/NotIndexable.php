<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hreflang;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Crawl\Urls;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A language version search engines may not show: closed with `noindex` or robots.txt — an
 * error, the pair is dropped — or with a canonical to another address — a warning, the
 * alternate should be the canonical one. The page also counts itself: a page with hreflang and a
 * canonical elsewhere names a set it is not in.
 */
final class NotIndexable extends HreflangCheck
{
    protected const ID = 'hreflang.not_indexable';

    protected const SEVERITY = Severity::ERROR;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $rows = [];
        $closed = false;

        foreach ($page->hreflang ?? [] as $alternate) {
            $target = $alternate['url'] === $page->url ? $page : $this->target($context, $alternate['url']);

            if ($target === null || $target->status !== 200) {
                continue;
            }

            if ($target->noindex() || $target->blocked_by_robots) {
                $rows[] = ['lang' => $alternate['lang'], 'url' => $alternate['url'], 'problem' => 'hreflang-closed'];
                $closed = true;
            } elseif ($target->canonical !== null && Urls::normalise($target->canonical) !== $target->url) {
                $rows[] = ['lang' => $alternate['lang'], 'url' => $alternate['url'], 'problem' => 'hreflang-not-canonical'];
            }
        }

        if ($rows !== []) {
            yield $this->on($page, 'hreflang-not-indexable', ['count' => count($rows)], [
                'columns' => [Finding::column('lang'), Finding::column('url', 'url'), Finding::column('problem', 'word')],
                'rows' => $rows,
            ], severity: $closed ? Severity::ERROR : Severity::WARNING);
        }
    }
}
