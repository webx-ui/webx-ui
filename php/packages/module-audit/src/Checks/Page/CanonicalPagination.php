<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * Page 2, 3, … of a list with a canonical to page 1: search engines take it at its word and drop
 * the later pages, and with them the only links to whatever is listed there. Each page of a list
 * names itself.
 */
final class CanonicalPagination extends CanonicalCheck
{
    protected const ID = 'canonical.pagination';

    protected const SEVERITY = Severity::WARNING;

    /** The usual names of the page number. */
    private const PARAMS = ['page', 'p', 'pg', 'paged', 'pagen', 'start'];

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $target = self::points($page);
        $query = (string) parse_url($page->url, PHP_URL_QUERY);

        if ($target === null || $query === '') {
            return;
        }

        parse_str($query, $params);

        foreach ($params as $name => $value) {
            if (! in_array(preg_replace('~_?\d+$~', '', strtolower((string) $name)), self::PARAMS, true) || ! is_numeric($value) || (int) $value < 2) {
                continue;
            }

            $rest = $params;
            unset($rest[$name]);
            $first = strtok($page->url, '?').($rest === [] ? '' : '?'.http_build_query($rest));

            if (rtrim($target, '/') === rtrim($first, '/')) {
                yield $this->on($page, 'canonical-pagination', ['url' => $target]);

                return;
            }
        }
    }
}
