<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Config;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** `APP_DEBUG=true` on a working domain: every error page prints the code and the environment. */
final class Debug extends Check
{
    protected const ID = 'config.debug';

    protected const GROUP = 'config';

    protected const SEVERITY = Severity::ERROR;

    protected const NEEDS = ['config'];

    public function run(AuditContext $context): iterable
    {
        if ($context->production() && (bool) $context->config('app.debug')) {
            yield $this->found('debug-on');
        }
    }
}
