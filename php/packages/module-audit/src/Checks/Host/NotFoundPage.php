<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** The 404 page has no link to the home page — a lost visitor has nowhere to go but back. */
final class NotFoundPage extends Check
{
    protected const ID = 'host.404_page';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::NOTICE;

    public function run(AuditContext $context): iterable
    {
        $answer = $context->probes->get('random');

        if ($answer === null || $answer->status !== 404 || $answer->body === '') {
            return;
        }

        $base = preg_quote($context->base(), '~');

        if (preg_match('~<a\b[^>]*\bhref\s*=\s*["\']?(?:/|'.$base.'/?)(?:["\'\s>])~i', $answer->body) !== 1) {
            yield $this->found('404-no-home', ['url' => $answer->url], $context->base().'/');
        }
    }
}
