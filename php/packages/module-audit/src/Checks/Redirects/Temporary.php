<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Redirects;

use WebxUi\Audit\Checks\Severity;

/**
 * 302 and 307: the search engine keeps the old address in the index and the new one gets none
 * of its weight. Right for a redirect that will be taken back — a list to look through.
 */
final class Temporary extends RedirectCheck
{
    protected const ID = 'redirects.temporary';

    protected const SEVERITY = Severity::NOTICE;

    protected function inspect(array $page, array $chain, bool $head): iterable
    {
        if (in_array($page['status'], [302, 307], true)) {
            yield $this->at($page, 'redirect-temporary', ['status' => $page['status'], 'location' => (string) $page['redirect_to']]);
        }
    }
}
