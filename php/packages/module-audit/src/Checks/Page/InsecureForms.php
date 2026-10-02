<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Page;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;

/**
 * A form that is sent over `http`.
 */
final class InsecureForms extends LinkCheck
{
    protected const ID = 'forms.insecure';

    protected const SEVERITY = Severity::ERROR;

    protected const SUMMARY = 'forms-insecure';

    protected function links(AuditContext $context): Builder
    {
        return $this->query($context)->where('kind', AuditLink::FORM)->where('to_url', 'like', 'http://%');
    }
}
