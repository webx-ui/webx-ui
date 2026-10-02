<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Indexing;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Page\PageCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Crawl\Urls;
use WebxUi\Audit\Runs\AuditPage;

/**
 * An address of the sitemap that is not a page to index: an error, a redirect, `noindex`, or a
 * canonical naming another page. The sitemap is a list of originals, and every such address
 * spends the search engine's patience on something it will throw away.
 */
final class SitemapBadUrl extends PageCheck
{
    protected const ID = 'sitemap.bad_url';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::ERROR;

    protected function pages(AuditContext $context): Builder
    {
        return AuditPage::query()->where('run_id', $context->run->id)->where('in_sitemap', true)->whereNotNull('fetched_at');
    }

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ($page->redirect_to !== null) {
            yield $this->on($page, 'sitemap-bad-redirect', ['status' => $page->status, 'location' => $page->redirect_to]);
        } elseif ($page->status !== 200) {
            yield $this->on($page, 'sitemap-bad-status', ['status' => $page->status ?? '—']);
        } elseif ($page->noindex()) {
            yield $this->on($page, 'sitemap-bad-noindex');
        } elseif ($page->canonical !== null && Urls::normalise($page->canonical) !== $page->url) {
            yield $this->on($page, 'sitemap-bad-canonical', ['url' => $page->canonical]);
        }
    }
}
