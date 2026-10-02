<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A button a screen reader can only call “button”.
 */
final class ButtonName extends PageCheck
{
    protected const ID = 'a11y.button_name';

    protected const GROUP = 'a11y';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $count = (int) $page->fact('buttons_unnamed', 0);

        if ($count > 0) {
            yield $this->on($page, 'button-name', ['count' => $count]);
        }
    }
}
