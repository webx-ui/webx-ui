<?php

declare(strict_types=1);

namespace WebxUi\Payments\Console;

use Illuminate\Console\Command;
use WebxUi\Payments\Online\Poll;

/**
 * The background poll of the gateways (spec §9.3), every five minutes by the schedule: attempts
 * that wait for an answer, attempts past their deadline, holds about to end.
 */
final class PollCommand extends Command
{
    protected $signature = 'webx:payments:poll';

    protected $description = 'Ask the gateways about unfinished payments and holds that end soon';

    public function handle(Poll $poll): int
    {
        $report = $poll->run();

        $this->components->info(sprintf(
            'Checked %d, expired %d, holds to warn of %d, errors %d.',
            $report['checked'],
            $report['expired'],
            $report['warned'],
            $report['errors'],
        ));

        return self::SUCCESS;
    }
}
