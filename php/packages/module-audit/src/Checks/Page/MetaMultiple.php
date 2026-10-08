<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditPage;

/**
 * A meta tag that should be there once written twice — two descriptions, two robots, two
 * `og:image`. Usually a layout and a module both print it; search engines pick one, not
 * necessarily the right one.
 */
final class MetaMultiple extends PageCheck
{
    protected const ID = 'meta.multiple';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(AuditPage $page, AuditContext $context): iterable
    {
        $repeated = $page->fact('meta_repeated');

        if (! is_array($repeated) || $repeated === []) {
            return;
        }

        $rows = [];

        foreach ($repeated as $name => $count) {
            $rows[] = ['value' => (string) $name, 'count' => (int) $count];
        }

        yield $this->on($page, 'meta-multiple', ['names' => implode(', ', array_column($rows, 'value'))], [
            'columns' => [Finding::column('value'), Finding::column('count')],
            'rows' => $rows,
        ]);
    }
}
