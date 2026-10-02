<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * An indexable address with a query and no canonical — every filter and sort is a page of its own.
 */
final class UrlParams extends PageCheck
{
    protected const ID = 'url.params';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::WARNING;

    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)->where('indexable', true)->whereNull('canonical');
    }

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if (parse_url($page->url, PHP_URL_QUERY) !== null && $page->fact('canonical_header') === null) {
            yield $this->on($page, 'url-params');
        }
    }
}
