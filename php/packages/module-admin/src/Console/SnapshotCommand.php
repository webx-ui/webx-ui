<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use WebxUi\Admin\Snapshots\SnapshotFailed;
use WebxUi\Admin\Snapshots\Snapshotter;

/**
 * The site's content in one file, to carry to another stand (`webx:snapshot:restore` there).
 *
 * What goes in is decided by the groups the packages declare (`SnapshotTables`): content always,
 * the admins on request, the stand's own life — enquiries, journals, tokens — only with `--all`.
 */
class SnapshotCommand extends Command
{
    protected $signature = 'webx:snapshot
        {--all : Every table but sessions, cache, queues and migrations — enquiries and journals included}
        {--with-admins : Add the panel\'s admins and roles}
        {--no-media : Leave the uploaded files out}
        {--output= : Where to write the archive (default: storage/app/snapshots/<site>-<env>-<date>.tar.gz)}';

    protected $description = 'Pack the site\'s content (database and uploaded files) into an archive for another stand';

    public function handle(Snapshotter $snapshots): int
    {
        $output = $this->option('output');
        $path = is_string($output) && $output !== '' ? $output : $snapshots->defaultPath();

        try {
            $made = $snapshots->make(
                $path,
                all: (bool) $this->option('all'),
                withAdmins: (bool) $this->option('with-admins'),
                media: ! $this->option('no-media'),
            );
        } catch (SnapshotFailed $failure) {
            $this->components->error($failure->getMessage());

            return self::FAILURE;
        }

        $manifest = $made['manifest'];
        $tables = $manifest->tables();

        $this->components->info($made['path']);
        $this->components->twoColumnDetail('Tables', sprintf('%d, %d rows', count($tables), array_sum(array_column($tables, 'rows'))));
        $this->components->twoColumnDetail('Files', $manifest->hasMedia()
            ? sprintf('%d, %s', count($manifest->mediaFiles()), self::size($manifest->mediaBytes()))
            : 'left out');
        $this->components->twoColumnDetail('Archive', self::size((int) filesize($made['path'])));

        if ($made['undeclared'] !== [] && ! $this->option('all')) {
            $this->components->warn(sprintf(
                'No package declares %s, so %s left out. Name %s in `webx-admin.snapshot.tables` to carry %s.',
                implode(', ', $made['undeclared']),
                count($made['undeclared']) === 1 ? 'it was' : 'they were',
                count($made['undeclared']) === 1 ? 'it' : 'them',
                count($made['undeclared']) === 1 ? 'it' : 'them',
            ));
        }

        return self::SUCCESS;
    }

    public static function size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return $unit === 0 ? $bytes.' B' : number_format($size, $size < 10 ? 1 : 0).' '.$units[$unit];
    }
}
