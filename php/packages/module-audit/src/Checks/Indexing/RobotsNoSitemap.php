<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Indexing;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Crawl\RobotsRules;

/**
 * robots.txt names no sitemap — search engines then find it only if someone told them by hand.
 */
final class RobotsNoSitemap extends Check
{
    protected const ID = 'robots.no_sitemap';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        $robots = $context->probes->get('robots');

        if ($robots !== null && $robots->ok() && RobotsRules::parse($robots->body)->sitemaps === []) {
            yield $this->found('robots-no-sitemap', [], $robots->url);
        }
    }
}
