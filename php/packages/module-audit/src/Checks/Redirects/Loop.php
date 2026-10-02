<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Redirects;

use WebxUi\Audit\Checks\Severity;

/**
 * Redirects that come back to where they started: the browser gives up with "too many
 * redirects", and the page does not exist for anyone. Said at every address that walks into the
 * loop — a loop has no head to say it at.
 */
final class Loop extends RedirectCheck
{
    protected const ID = 'redirects.loop';

    protected const SEVERITY = Severity::ERROR;

    protected function inspect(array $page, array $chain, bool $head): iterable
    {
        if ($chain['loop']) {
            yield $this->at($page, 'redirect-loop', ['steps' => count($chain['steps'])], $this->table($chain));
        }
    }
}
