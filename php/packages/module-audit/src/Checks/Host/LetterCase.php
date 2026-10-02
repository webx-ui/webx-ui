<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** `/About` answers 200 rather than a 301 to `/about`. */
final class LetterCase extends Check
{
    protected const ID = 'host.case';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        $answer = $context->probes->get('case');

        if ($answer !== null && $answer->ok()) {
            yield $this->found('letter-case', ['path' => (string) parse_url($answer->url, PHP_URL_PATH)], $answer->url);
        }
    }
}
