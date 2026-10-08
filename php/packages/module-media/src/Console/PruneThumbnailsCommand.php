<?php

declare(strict_types=1);

namespace WebxUi\Media\Console;

use Illuminate\Console\Command;
use WebxUi\Media\Images\OrphanThumbnails;

/**
 * The previews of files that are no longer in the library, off the disk — the same sweep as
 * the audit's fix for `media.orphan_thumbs`.
 */
final class PruneThumbnailsCommand extends Command
{
    protected $signature = 'webx:media:prune-thumbs
        {--dry-run : List the folders and delete nothing}';

    protected $description = 'Delete the preview folders of files that are no longer in the media library';

    public function handle(OrphanThumbnails $orphans): int
    {
        $found = $orphans->find();

        foreach ($found as $orphan) {
            $this->line("{$orphan['disk']}:{$orphan['path']}");
        }

        if ($found === []) {
            $this->info('No orphaned previews.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info(count($found).' folder(s) would be deleted.');

            return self::SUCCESS;
        }

        $this->info($orphans->sweep().' folder(s) deleted.');

        return self::SUCCESS;
    }
}
