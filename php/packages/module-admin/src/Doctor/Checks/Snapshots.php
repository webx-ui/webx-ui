<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Foundation\Application;
use WebxUi\Admin\Console\SnapshotCommand;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\Paths;
use WebxUi\Admin\Snapshots\Snapshotter;

/**
 * Archives of `webx:snapshot` left lying in storage.
 *
 * Each is the whole of the site's content, and one made with `--all` holds every enquiry too. An
 * archive is for carrying to another stand and restoring there, not for keeping: a week after it
 * was made it is a copy nobody remembers, and it grows by the size of the media library each time.
 */
final class Snapshots implements Check
{
    private const STALE_DAYS = 7;

    public function __construct(
        private readonly Application $app,
        private readonly Snapshotter $snapshots,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $directory = $this->snapshots->directory();
        $archives = glob($directory.DIRECTORY_SEPARATOR.'*.tar.gz') ?: [];

        if ($archives === []) {
            return [];
        }

        $where = Paths::short($directory, $this->app->basePath());
        $bytes = array_sum(array_map(static fn (string $file): int => (int) filesize($file), $archives));
        $oldest = min(array_map(static fn (string $file): int => (int) filemtime($file), $archives));
        $summary = count($archives).' in '.$where.', '.SnapshotCommand::size($bytes);

        if ($oldest < time() - self::STALE_DAYS * 86400) {
            return [Diagnosis::warn(
                'Snapshots',
                $summary.', the oldest from '.date('Y-m-d', $oldest).'. Each holds the site\'s content, and one made with --all its enquiries — delete the ones already restored.',
            )];
        }

        return [Diagnosis::ok('Snapshots', $summary.'.')];
    }
}
