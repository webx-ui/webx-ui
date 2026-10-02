<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Config;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Check;
use WebxUi\Audit\Checks\Severity;

/**
 * The scheduler has not run for over an hour: backups, the journal's trim and the sitemap's
 * dates stop without a word. The module puts a heartbeat on the schedule to know.
 */
final class Schedule extends Check
{
    /** Where the heartbeat writes the time it last ran. */
    public const HEARTBEAT = 'webx-audit:schedule-heartbeat';

    protected const ID = 'config.schedule';

    protected const GROUP = 'config';

    protected const SEVERITY = Severity::WARNING;

    protected const NEEDS = ['config'];

    public function __construct(private readonly Cache $cache) {}

    public function run(AuditContext $context): iterable
    {
        // An array store forgets between processes, so it can say nothing either way.
        if (! $context->production() || $context->config('cache.default') === 'array') {
            return;
        }

        $beat = $this->cache->get(self::HEARTBEAT);
        $limit = $context->threshold('schedule_minutes', 60);

        if (! is_numeric($beat)) {
            yield $this->found('schedule-never');

            return;
        }

        $minutes = (int) floor((Carbon::now()->getTimestamp() - (int) $beat) / 60);

        if ($minutes > $limit) {
            yield $this->found('schedule-stale', ['minutes' => $minutes]);
        }
    }
}
