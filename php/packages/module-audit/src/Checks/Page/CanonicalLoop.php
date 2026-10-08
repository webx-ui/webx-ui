<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * Two pages that name each other as the original: neither is, and search engines ignore both
 * canonicals.
 */
final class CanonicalLoop extends CanonicalCheck
{
    protected const ID = 'canonical.loop';

    protected const SEVERITY = Severity::ERROR;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $target = self::points($page);
        $other = $target === null ? null : self::target($page, $target);

        if ($other !== null && $other->status === 200 && self::points($other) === $page->url) {
            yield $this->on($page, 'canonical-loop', ['url' => $target]);
        }
    }
}
