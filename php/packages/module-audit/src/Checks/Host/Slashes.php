<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** `/a//b` answers 200 rather than a 301 to `/a/b`. */
final class Slashes extends Check
{
    protected const ID = 'host.slashes';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        $answer = $context->probes->get('slashes');

        if ($answer !== null && $answer->ok()) {
            yield $this->found('double-slash', ['path' => (string) parse_url($answer->url, PHP_URL_PATH)], $answer->url);
        }
    }
}
