<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/**
 * The site answers HTTPS over HTTP/1.1 only: a page with forty pictures and scripts waits for
 * them six at a time instead of all at once. Said only when curl could have spoken HTTP/2 —
 * without that, nothing is known either way.
 */
final class Http2 extends Check
{
    protected const ID = 'host.http2';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::NOTICE;

    public function run(AuditContext $context): iterable
    {
        $home = $context->probes->get('home');

        if ($home !== null && $home->status !== null && $home->protocol !== null && ! str_starts_with($home->protocol, '2') && ! str_starts_with($home->protocol, '3')) {
            yield $this->found('http2', ['protocol' => $home->protocol], $home->url);
        }
    }
}
