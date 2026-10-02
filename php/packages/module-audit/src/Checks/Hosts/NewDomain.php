<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Hosts;

use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditRun;

/**
 * An outside domain the previous full run did not see — a typo in a fresh link, or spam links
 * left by somebody who got into the site. Quiet on the first crawl and when the previous
 * snapshot is gone: with nothing to compare, everything would be "new".
 */
final class NewDomain extends HostCheck
{
    protected const ID = 'hosts.new_domain';

    protected const SEVERITY = Severity::NOTICE;

    protected const SUMMARY = 'new-domain';

    protected function hosts(AuditContext $context, array $hosts): array
    {
        $previous = $context->run->previous();

        if ($previous === null || $previous->scope !== AuditRun::FULL || ! AuditLink::query()->where('run_id', $previous->id)->exists()) {
            return [];
        }

        $before = AuditLink::query()->where('run_id', $previous->id)->whereNotNull('host')->distinct()->pluck('host')->flip()->all();

        return array_values(array_filter(
            $hosts,
            fn (string $host): bool => ! isset($before[$host]) && $context->hosts->classifyHost($host) === 'external',
        ));
    }
}
