<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;

/** No `X-Content-Type-Options`, `Referrer-Policy`, or protection from being framed. */
final class SecurityHeaders extends Check
{
    protected const ID = 'host.security_headers';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::NOTICE;

    public function run(AuditContext $context): iterable
    {
        $home = $context->probes->get('home');

        if ($home === null || ! $home->ok()) {
            return;
        }

        $missing = [];

        if ($home->header('x-content-type-options') === null) {
            $missing[] = 'X-Content-Type-Options';
        }

        if ($home->header('referrer-policy') === null) {
            $missing[] = 'Referrer-Policy';
        }

        if ($home->header('x-frame-options') === null && ! str_contains(strtolower((string) $home->header('content-security-policy')), 'frame-ancestors')) {
            $missing[] = 'X-Frame-Options';
        }

        if ($missing !== []) {
            yield $this->found('security-headers', ['headers' => implode(', ', $missing)], $home->url, table: [
                'columns' => [Finding::column('header'), Finding::column('value', 'missing')],
                'rows' => array_map(static fn (string $header): array => ['header' => $header, 'value' => null], $missing),
            ]);
        }
    }
}
