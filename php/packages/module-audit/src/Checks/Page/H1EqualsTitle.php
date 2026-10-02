<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * The H1 word for word the title — one of them could say more.
 */
final class H1EqualsTitle extends PageCheck
{
    protected const ID = 'h1.equals_title';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $h1 = trim((string) ($page->h1[0] ?? ''));

        if ($h1 !== '' && mb_strtolower($h1) === mb_strtolower(trim((string) $page->title))) {
            yield $this->on($page, 'h1-equals-title', ['value' => $h1]);
        }
    }
}
