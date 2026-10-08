<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Crawl\Urls;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A canonical that leads to a redirect, an error or a closed page — the page names a copy that cannot be the original.
 */
final class CanonicalBroken extends PageCheck
{
    protected const ID = 'canonical.broken';

    protected const SEVERITY = Severity::ERROR;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $target = $page->canonical === null ? null : Urls::normalise($page->canonical);

        if ($target === null || $target === $page->url) {
            return;
        }

        $other = AuditPage::query()->where('run_id', $page->run_id)->where('url_hash', AuditPage::hash($target))->whereNotNull('fetched_at')->first();

        if ($other === null) {
            return;
        }

        if ($other->status !== 200) {
            yield $this->on($page, 'canonical-broken', ['url' => $target, 'status' => $other->status ?? '—']);
        } elseif ($other->noindex()) {
            yield $this->on($page, 'canonical-noindex', ['url' => $target]);
        } elseif ($other->blocked_by_robots) {
            yield $this->on($page, 'canonical-robots', ['url' => $target]);
        }
    }
}
