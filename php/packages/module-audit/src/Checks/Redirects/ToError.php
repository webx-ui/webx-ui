<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Redirects;

use WebxUi\Audit\Checks\Severity;

/**
 * A redirect that ends on 4xx, 5xx or no answer at all — the old address is kept alive only to
 * lead to a dead one.
 */
final class ToError extends RedirectCheck
{
    protected const ID = 'redirects.to_error';

    protected const SEVERITY = Severity::ERROR;

    protected function inspect(array $page, array $chain, bool $head): iterable
    {
        $end = $chain['end'];

        if ($head && $end !== null && ($end['status'] === null || $end['status'] >= 400)) {
            yield $this->at($page, 'redirect-to-error', ['url' => $end['url'], 'status' => $end['status'] ?? '—'], $this->table($chain));
        }
    }
}
