<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/**
 * An address that cannot exist does not answer 404: it answers 200, or sends the visitor home.
 * Every typo and every deleted page then looks like a page to a search engine.
 */
final class Soft404 extends Check
{
    protected const ID = 'host.soft_404';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::ERROR;

    public function run(AuditContext $context): iterable
    {
        $answer = $context->probes->get('random');

        if ($answer === null || $answer->status === null || in_array($answer->status, [404, 410], true)) {
            return;
        }

        if ($answer->redirect()) {
            yield $this->found('soft-404-redirect', ['status' => $answer->status, 'location' => (string) $answer->location(), 'url' => $answer->url], $context->base().'/');

            return;
        }

        yield $this->found('soft-404', ['status' => $answer->status, 'url' => $answer->url], $context->base().'/');
    }
}
