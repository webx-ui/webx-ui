<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Config;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** `APP_ENV` other than `production` on a working domain. */
final class Environment extends Check
{
    protected const ID = 'config.env';

    protected const GROUP = 'config';

    protected const SEVERITY = Severity::WARNING;

    protected const NEEDS = ['config'];

    public function run(AuditContext $context): iterable
    {
        $env = (string) $context->config('app.env', 'production');

        if ($context->production() && $env !== 'production') {
            yield $this->found('env', ['env' => $env]);
        }
    }
}
