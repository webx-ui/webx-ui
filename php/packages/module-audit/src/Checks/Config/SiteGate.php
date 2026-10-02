<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Config;

use WebxUi\Admin\Gate\Credentials;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** The password over the site is on: no search engine sees a page of it. */
final class SiteGate extends Check
{
    protected const ID = 'config.site_gate';

    protected const GROUP = 'config';

    protected const SEVERITY = Severity::NOTICE;

    protected const NEEDS = ['config'];

    public function __construct(private readonly Credentials $credentials) {}

    public function run(AuditContext $context): iterable
    {
        if ($this->credentials->enabled()) {
            yield $this->found('site-gate');
        }
    }
}
