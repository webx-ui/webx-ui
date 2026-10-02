<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/** HTML goes out without gzip or brotli — several times the bytes on every page. */
final class Compression extends Check
{
    protected const ID = 'host.compression';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::WARNING;

    public function run(AuditContext $context): iterable
    {
        $home = $context->probes->get('home');

        if ($home === null || ! $home->ok() || ! str_contains((string) $home->header('content-type'), 'html')) {
            return;
        }

        // Guzzle moves the header aside when curl decodes the body.
        $encoding = strtolower((string) ($home->header('content-encoding') ?? $home->header('x-encoded-content-encoding')));

        if (preg_match('~\b(gzip|br|deflate|zstd)\b~', $encoding) !== 1) {
            yield $this->found('compression', [], $home->url);
        }
    }
}
