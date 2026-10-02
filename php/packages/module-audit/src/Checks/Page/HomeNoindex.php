<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * The home page closed to search engines — the whole site goes with it.
 */
final class HomeNoindex extends PageCheck
{
    protected const ID = 'indexing.home_noindex';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::ERROR;

    protected function pages(AuditContext $context): Builder
    {
        return parent::pages($context)->where('source', AuditPage::HOME);
    }

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ($page->noindex()) {
            yield $this->on($page, 'noindex', ['source' => Noindex::source($page)]);
        }
    }
}
