<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * No `<!doctype html>`: the browser draws the page in quirks mode, with the box sizes and the
 * table layout of the nineties.
 */
final class HtmlDoctype extends PageCheck
{
    protected const ID = 'html.doctype';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        // A snapshot made before the fact was read says nothing either way.
        if ($page->fact('doctype') === false) {
            yield $this->on($page, 'html-doctype');
        }
    }
}
