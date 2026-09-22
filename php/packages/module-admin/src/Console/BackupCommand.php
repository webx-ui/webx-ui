<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use WebxUi\Admin\Backups\BackupFailed;
use WebxUi\Admin\Backups\Backups;

/**
 * A gzipped dump of the database, once a night.
 *
 * The rotation runs only after a dump has succeeded, and that order is the point: clearing out
 * last week without having written tonight is the worst thing a backup command can do, and it
 * is exactly what happens if the two steps are written the other way round.
 */
class BackupCommand extends Command
{
    protected $signature = 'webx:db:backup {--keep= : How many days of snapshots to keep}';

    protected $description = 'Write a compressed dump of the database to the backups directory on the local disk';

    public function handle(Backups $backups): int
    {
        $keep = $this->option('keep') === null ? null : max(1, (int) $this->option('keep'));

        try {
            $snapshot = $backups->take();
        } catch (BackupFailed $failure) {
            // The panel's line says a snapshot is missing; the log says why. Nobody is awake
            // at ten past three to read a console.
            Log::error('The nightly database backup failed. '.$failure->getMessage());
            $this->components->error($failure->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%s — %s.',
            $snapshot->name(),
            $this->size($snapshot->bytes),
        ));

        foreach ($backups->prune($keep) as $name) {
            $this->components->twoColumnDetail($name, 'removed');
        }

        return self::SUCCESS;
    }

    private function size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return $unit === 0
            ? $bytes.' B'
            : number_format($size, $size < 10 ? 1 : 0).' '.$units[$unit];
    }
}
