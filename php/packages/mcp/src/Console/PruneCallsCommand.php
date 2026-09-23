<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use WebxUi\Mcp\Calls\Call;

/**
 * Remove tool calls older than the retention.
 *
 * On the schedule nightly, the way the sign-in trail is kept by days; here by hand for the day
 * the retention was shortened and the rows above it are still there.
 */
class PruneCallsCommand extends Command
{
    protected $signature = 'webx:mcp:prune-calls';

    protected $description = 'Remove tool calls older than the configured retention';

    public function handle(Config $config): int
    {
        $days = $config->get('webx-mcp.calls.days');

        if (! is_int($days) || $days < 1) {
            $this->info('The call log is kept forever: set webx-mcp.calls.days to prune it.');

            return self::SUCCESS;
        }

        $removed = Call::query()->olderThan($days)->delete();

        $this->info(sprintf(
            'Removed %d call%s older than %d day%s.',
            $removed,
            $removed === 1 ? '' : 's',
            $days,
            $days === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }
}
