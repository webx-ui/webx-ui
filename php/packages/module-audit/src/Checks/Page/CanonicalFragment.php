<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A canonical with `#…` in it. Search engines drop the fragment, or the whole canonical; either
 * way it does not say what it was meant to.
 */
final class CanonicalFragment extends PageCheck
{
    protected const ID = 'canonical.fragment';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        foreach ((array) $page->fact('canonicals', []) as $canonical) {
            if (is_string($canonical) && str_contains($canonical, '#')) {
                yield $this->on($page, 'canonical-fragment', ['url' => $canonical]);

                return;
            }
        }
    }
}
