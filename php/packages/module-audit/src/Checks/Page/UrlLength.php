<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * An address longer than the threshold.
 */
final class UrlLength extends PageCheck
{
    protected const ID = 'url.length';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $length = mb_strlen($page->url);

        if ($length > $context->threshold('url_length', 115)) {
            yield $this->on($page, 'url-length', ['length' => $length]);
        }
    }
}
