<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * More than 1000 internal links on one page — a mega-menu or a list without pages. Each link
 * gets a sliver of the page's weight, and search engines stop reading somewhere along the way.
 */
final class InternalMany extends PageCheck
{
    protected const ID = 'structure.many_internal';

    protected const GROUP = 'structure';

    protected const SEVERITY = Severity::NOTICE;

    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)->where('links_out_internal', '>', $context->threshold('internal_links', 1000));
    }

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        yield $this->on($page, 'internal-many', ['count' => $page->links_out_internal, 'max' => $context->threshold('internal_links', 1000)]);
    }
}
