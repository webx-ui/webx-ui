<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A heading with no text — an icon in an H3, or a block left without its title.
 */
final class HeadingsEmpty extends PageCheck
{
    protected const ID = 'headings.empty';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $levels = [];

        foreach (Outline::of($page) as [$level, $text]) {
            if ($text === '') {
                $levels[] = 'H'.$level;
            }
        }

        if ($levels !== []) {
            yield $this->on($page, 'headings-empty', [
                'levels' => implode(', ', array_unique($levels)),
                'count' => count($levels),
            ]);
        }
    }
}
