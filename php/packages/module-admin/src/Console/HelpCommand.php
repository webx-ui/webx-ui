<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use WebxUi\Admin\Help\HelpAudit;
use WebxUi\Admin\Help\HelpRegistry;

/**
 * The help of this site, with every module it has.
 *
 * A package's test sees the package and its core; the fields a neighbour adds, and the ones a
 * project patches in, only exist here (WEBX_UI_PANEL_HELP.md §6.4). So `--check` is the one place
 * that can say a form on this very site has a field nobody explained. It reads and reports: a
 * field without a text costs the panel nothing but the text.
 */
final class HelpCommand extends Command
{
    protected $signature = 'webx:help
                            {--check : Say what on this site is not explained, and exit 1 if anything is}';

    protected $description = 'List the pages of help, or check them against the screens of this site';

    public function handle(HelpRegistry $help, HelpAudit $audit): int
    {
        if (! $this->option('check')) {
            foreach ($help->keys() as $key) {
                $this->line($key);
            }

            return self::SUCCESS;
        }

        $problems = $audit->problems();

        foreach ($problems as $problem) {
            $this->line($problem);
        }

        if ($problems === []) {
            $this->components->info('Every screen and field of this site is explained.');

            return self::SUCCESS;
        }

        $this->components->warn(count($problems).' to explain.');

        return self::FAILURE;
    }
}
