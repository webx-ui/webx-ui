<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Crawl\Urls;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A check of a canonical that names another page: the pages are those, and the page it names
 * is looked up in the snapshot — the crawl queues every canonical, so it has usually been asked.
 */
abstract class CanonicalCheck extends PageCheck
{
    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)->whereNotNull('canonical')->whereColumn('canonical', '<>', 'url');
    }

    /** Where the canonical points, normalised, or null when it points at the page itself. */
    protected static function points(AuditPage $page): ?string
    {
        $target = $page->canonical === null ? null : Urls::normalise($page->canonical);

        return $target === null || $target === $page->url ? null : $target;
    }

    /** The page the canonical names, if the crawl asked it. */
    protected static function target(AuditPage $page, string $url): ?AuditPage
    {
        return AuditPage::query()->where('run_id', $page->run_id)->where('url_hash', AuditPage::hash($url))->whereNotNull('fetched_at')->first();
    }
}
