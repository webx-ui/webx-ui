<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use Illuminate\Support\Carbon;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/**
 * The certificate runs out soon, names another host, or does not chain to a trusted root.
 * Silent when it could not be read at all — no TLS on the host is `host.https`'s business.
 */
final class Tls extends Check
{
    protected const ID = 'host.tls';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::ERROR;

    public function run(AuditContext $context): iterable
    {
        $certificate = $context->probes->certificate;

        if ($certificate === null) {
            return;
        }

        $url = $context->base().'/';

        if (! $certificate->matchesHost) {
            yield $this->found('tls-host', ['host' => $context->host()], $url, key: 'host');
        }

        if (! $certificate->trusted) {
            yield $this->found('tls-untrusted', ['issuer' => $certificate->issuer], $url, key: 'chain');
        }

        $days = $certificate->daysLeft(Carbon::now()->getTimestamp());

        if ($days < $context->threshold('tls_error_days', 14)) {
            yield $this->found('tls-expires', ['days' => $days], $url, key: 'expiry');
        } elseif ($days < $context->threshold('tls_warning_days', 30)) {
            yield $this->found('tls-expires', ['days' => $days], $url, Severity::WARNING, key: 'expiry');
        }
    }
}
