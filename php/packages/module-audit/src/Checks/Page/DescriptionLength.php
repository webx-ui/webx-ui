<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A description shorter or longer than the thresholds, in characters.
 */
final class DescriptionLength extends PageCheck
{
    protected const ID = 'description.length';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $length = mb_strlen(trim((string) $page->description));
        $min = $context->threshold('description_min', 70);
        $max = $context->threshold('description_max', 160);

        if ($length > 0 && ($length < $min || $length > $max)) {
            yield $this->on($page, 'description-length', ['length' => $length, 'min' => $min, 'max' => $max]);
        }
    }
}
