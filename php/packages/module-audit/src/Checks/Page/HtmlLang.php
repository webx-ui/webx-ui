<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * No `lang` on `<html>`. Whether it agrees with hreflang is A3, with the hreflang checks.
 */
final class HtmlLang extends PageCheck
{
    protected const ID = 'html.lang';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        if ($page->lang === null || trim($page->lang) === '') {
            yield $this->on($page, 'lang-missing');
        }
    }
}
