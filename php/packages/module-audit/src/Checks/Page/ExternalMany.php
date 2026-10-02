<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * More external links on a page than the threshold.
 */
final class ExternalMany extends PageCheck
{
    protected const ID = 'hosts.external_many';

    protected const GROUP = 'hosts';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ($page->links_out_external > $context->threshold('external_links', 100)) {
            yield $this->on($page, 'external-many', ['count' => $page->links_out_external]);
        }
    }
}
