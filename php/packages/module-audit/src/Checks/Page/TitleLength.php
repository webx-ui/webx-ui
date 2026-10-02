<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A title shorter or longer than the thresholds, in characters (decision 10).
 */
final class TitleLength extends PageCheck
{
    protected const ID = 'title.length';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $length = mb_strlen(trim((string) $page->title));
        $min = $context->threshold('title_min', 30);
        $max = $context->threshold('title_max', 60);

        if ($length > 0 && ($length < $min || $length > $max)) {
            yield $this->on($page, 'title-length', ['length' => $length, 'min' => $min, 'max' => $max]);
        }
    }
}
