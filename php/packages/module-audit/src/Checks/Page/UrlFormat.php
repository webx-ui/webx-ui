<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * Capitals, underscores or characters outside ASCII in the path — one finding for each.
 */
final class UrlFormat extends PageCheck
{
    protected const ID = 'url.format';

    protected const GROUP = 'content';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $path = rawurldecode((string) parse_url($page->url, PHP_URL_PATH));

        if (preg_match('/[A-Z]/', $path) === 1) {
            yield $this->on($page, 'url-uppercase', key: 'uppercase');
        }

        if (str_contains($path, '_')) {
            yield $this->on($page, 'url-underscore', key: 'underscore');
        }

        if (preg_match('/[^\x00-\x7F]/', $path) === 1) {
            yield $this->on($page, 'url-non-ascii', key: 'non-ascii');
        }
    }
}
