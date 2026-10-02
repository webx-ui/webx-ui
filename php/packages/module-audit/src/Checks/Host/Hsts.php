<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** No `Strict-Transport-Security`: the first visit can still be made over plain http. */
final class Hsts extends Check
{
    protected const ID = 'host.hsts';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::NOTICE;

    public function run(AuditContext $context): iterable
    {
        $home = $context->probes->get('home');

        if ($context->scheme() === 'https' && $home !== null && $home->ok() && $home->header('strict-transport-security') === null) {
            yield $this->found('hsts', [], $home->url);
        }
    }
}
