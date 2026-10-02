<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Redirects;

use WebxUi\Audit\Checks\Severity;

/**
 * More than one step from an address to the page: every step is a round trip for a visitor
 * and a step a search engine may stop following.
 */
final class Chain extends RedirectCheck
{
    protected const ID = 'redirects.chain';

    protected const SEVERITY = Severity::WARNING;

    protected function inspect(array $page, array $chain, bool $head): iterable
    {
        if ($head && ! $chain['loop'] && count($chain['steps']) > 1) {
            yield $this->at($page, 'redirect-chain', ['steps' => count($chain['steps'])], $this->table($chain));
        }
    }
}
