<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A canonical to a page whose own canonical leads further on. Search engines follow a step or
 * two, or give up and choose for themselves; name the last page straight away.
 */
final class CanonicalChain extends CanonicalCheck
{
    protected const ID = 'canonical.chain';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $target = self::points($page);
        $other = $target === null ? null : self::target($page, $target);
        $next = $other === null || $other->status !== 200 ? null : self::points($other);

        // Back to where it started is a loop — `canonical.loop` says that.
        if ($next !== null && $next !== $page->url) {
            yield $this->on($page, 'canonical-chain', ['url' => $target, 'next' => $next]);
        }
    }
}
