<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;
use WebxUi\Catalog\Exchange\ExchangeFiles;
use WebxUi\Catalog\Exchange\ExchangeRun;

/**
 * What the exchange keeps, taken away when it has been kept long enough (§5, §6 of the exchange
 * spec): the files of finished runs after `exchange.keep_hours` — an export's link dies with its
 * file — and the runs themselves, their errors with them, after `exchange.keep_runs_days`. On the
 * schedule every hour.
 */
class ExchangePruneCommand extends Command
{
    protected $signature = 'webx:catalog:exchange-prune';

    protected $description = 'Remove the files and the runs of the catalogue exchange that have been kept long enough';

    public function handle(ExchangeFiles $files): int
    {
        $hours = max(1, (int) config('webx-catalog.exchange.keep_hours', 24));
        $days = max(1, (int) config('webx-catalog.exchange.keep_runs_days', 90));
        $filesGone = 0;
        $runsGone = 0;

        ExchangeRun::query()
            ->whereNotNull('file')
            ->whereIn('status', [ExchangeRun::DONE, ExchangeRun::STOPPED, ExchangeRun::FAILED])
            ->where('finished_at', '<', Carbon::now()->subHours($hours))
            ->chunkById(200, function ($runs) use ($files, &$filesGone): void {
                foreach ($runs as $run) {
                    self::erase($files, $run);
                    $run->forceFill(['file' => null])->save();
                    $filesGone++;
                }
            });

        ExchangeRun::query()
            ->where('created_at', '<', Carbon::now()->subDays($days))
            ->whereIn('status', [ExchangeRun::DONE, ExchangeRun::STOPPED, ExchangeRun::FAILED])
            ->chunkById(200, function ($runs) use ($files, &$runsGone): void {
                foreach ($runs as $run) {
                    self::erase($files, $run);
                    $run->delete();
                    $runsGone++;
                }
            });

        $this->info(sprintf('Exchange: %d file(s) and %d run(s) removed.', $filesGone, $runsGone));

        return self::SUCCESS;
    }

    private static function erase(ExchangeFiles $files, ExchangeRun $run): void
    {
        if ($run->file === null) {
            return;
        }

        try {
            $files->disk()->delete($run->file);
        } catch (Throwable) {
            // Gone already: the row is what the prune is about.
        }
    }
}
