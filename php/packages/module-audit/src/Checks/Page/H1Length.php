<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * An H1 longer than 70 characters: a heading that has turned into a sentence, and on a phone
 * one that fills the first screen.
 */
final class H1Length extends PageCheck
{
    protected const ID = 'h1.length';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $h1 = (string) ($page->h1[0] ?? '');
        $max = $context->threshold('h1_max', 70);
        $length = mb_strlen($h1);

        if ($length > $max) {
            yield $this->on($page, 'h1-long', ['length' => $length, 'max' => $max]);
        }
    }
}
