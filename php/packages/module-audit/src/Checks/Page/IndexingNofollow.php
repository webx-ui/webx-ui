<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * `nofollow` (or `none`) in the robots meta tag or `X-Robots-Tag`: search engines do not follow
 * a single link of the page — every page it leads to loses it.
 */
final class IndexingNofollow extends PageCheck
{
    protected const ID = 'indexing.nofollow';

    protected const GROUP = 'indexing';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $robots = strtolower(($page->robots_meta ?? '').','.($page->x_robots_tag ?? ''));

        if (preg_match('~\b(nofollow|none)\b~', $robots) === 1) {
            yield $this->on($page, 'indexing-nofollow', ['value' => trim($robots, ',')]);
        }
    }
}
