<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Host;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;

/** `X-Powered-By`, or a `Server` with its version: a map for whoever looks for a known hole. */
final class ServerLeak extends Check
{
    protected const ID = 'host.server_leak';

    protected const GROUP = 'host';

    protected const SEVERITY = Severity::NOTICE;

    public function run(AuditContext $context): iterable
    {
        $home = $context->probes->get('home');

        if ($home === null || $home->status === null) {
            return;
        }

        $rows = [];

        if (($powered = $home->header('x-powered-by')) !== null) {
            $rows[] = ['header' => 'X-Powered-By', 'value' => $powered];
        }

        $server = $home->header('server');

        if ($server !== null && preg_match('~\d~', $server) === 1) {
            $rows[] = ['header' => 'Server', 'value' => $server];
        }

        if ($rows !== []) {
            yield $this->found('server-leak', ['headers' => implode(', ', array_column($rows, 'header'))], $home->url, table: [
                'columns' => [Finding::column('header'), Finding::column('value')],
                'rows' => $rows,
            ]);
        }
    }
}
