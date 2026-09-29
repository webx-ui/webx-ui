<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use WebxUi\Admin\Uploads\Uploads;

/**
 * Drop the chunked uploads nobody has sent a piece to within `webx-admin.uploads.ttl_hours`,
 * with their files. Put on the schedule by the frame, hourly: an abandoned upload is up to the
 * size of the largest file anybody may send, and a day of them is a full disk.
 */
class PruneUploadsCommand extends Command
{
    protected $signature = 'webx:prune-uploads';

    protected $description = 'Remove chunked uploads left unfinished for longer than webx-admin.uploads.ttl_hours';

    public function handle(Uploads $uploads): int
    {
        $removed = $uploads->prune();

        $this->info(sprintf(
            'Removed %d unfinished upload%s older than %d hour%s.',
            $removed,
            $removed === 1 ? '' : 's',
            $uploads->ttlHours(),
            $uploads->ttlHours() === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }
}
