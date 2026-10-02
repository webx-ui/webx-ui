<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A field without a `label` or an `aria-label` — a placeholder is not a label.
 */
final class FormLabel extends PageCheck
{
    protected const ID = 'a11y.form_label';

    protected const GROUP = 'a11y';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $count = (int) $page->fact('fields_unlabeled', 0);

        if ($count > 0) {
            yield $this->on($page, 'form-label', ['count' => $count]);
        }
    }
}
