<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * In the sitemap or the registry, but no page of the site links to it — search engines and visitors reach it only by accident.
 */
final class Orphan extends PageCheck
{
    protected const ID = 'structure.orphan';

    protected const GROUP = 'structure';

    protected const SEVERITY = Severity::WARNING;

    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)
            ->where('indexable', true)
            ->where('links_in', 0)
            ->where('source', '<>', AuditPage::HOME)
            ->where(static fn (Builder $listed) => $listed->where('in_sitemap', true)->orWhere('in_registry', true));
    }

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        yield $this->on($page, 'orphan');
    }
}
