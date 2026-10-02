<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hosts;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Page\LinkCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Hosts\HostClassifier;

/**
 * A link or a resource of the page — a picture, a script, a style, `og:image`, the canonical — on a development stand (§5.6).
 */
final class DevPage extends LinkCheck
{
    protected const ID = 'hosts.dev_page';

    protected const SEVERITY = Severity::ERROR;

    protected const GROUP = 'hosts';

    protected const SUMMARY = 'dev-page';

    protected function links(AuditContext $context): Builder
    {
        return $this->query($context)->where('host_class', HostClassifier::DEV);
    }
}
