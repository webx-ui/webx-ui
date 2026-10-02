<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Indexing;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Crawl\RobotsRules;

/**
 * `Disallow: /` for every robot on a working domain — the stand's robots.txt that came along
 * with the deploy. Quiet on a stand, where closing everything is the point.
 */
final class RobotsDisallowAll extends Check
{
    protected const ID = 'robots.disallow_all';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::ERROR;

    public function run(AuditContext $context): iterable
    {
        $robots = $context->probes->get('robots');

        if ($robots === null || ! $robots->ok() || ! $context->production()) {
            return;
        }

        if (RobotsRules::parse($robots->body)->blocks('/')) {
            yield $this->found('robots-disallow-all', [], $robots->url);
        }
    }
}
