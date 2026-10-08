<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * Links nobody can follow: `#` and `javascript:` — a button dressed as a link, a notice — and
 * the plainly broken ones, a warning: `mailto:` without an address, `tel:` without a number, a
 * scheme glued into a path, `www.` without `https://`.
 */
final class Unfollowable extends PageCheck
{
    protected const ID = 'links.unfollowable';

    protected const GROUP = 'links';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $found = $page->fact('links_unfollowable');

        if (! is_array($found) || (int) ($found['count'] ?? 0) === 0) {
            return;
        }

        yield $this->on($page, 'links-unfollowable', ['count' => (int) $found['count']], [
            'columns' => [Finding::column('markup', 'code')],
            'rows' => array_map(static fn (mixed $href): array => ['markup' => 'href="'.$href.'"'], (array) ($found['hrefs'] ?? [])),
        ], severity: (int) ($found['broken'] ?? 0) > 0 ? Severity::WARNING : Severity::NOTICE);
    }
}
