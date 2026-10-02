<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Indexing;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Page\PageCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A page to index that the crawl reached and the sitemap does not list. Quiet when there is no
 * sitemap at all — `sitemap.missing` says that once instead of on every page.
 */
final class SitemapMissingPage extends PageCheck
{
    protected const ID = 'sitemap.missing_page';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        if (! AuditPage::query()->where('run_id', $context->run->id)->where('in_sitemap', true)->exists()) {
            return;
        }

        yield from parent::run($context);
    }

    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)->where('indexable', true)->where('in_sitemap', false);
    }

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        yield $this->on($page, 'sitemap-missing-page');
    }
}
