<?php

declare(strict_types=1);

namespace WebxUi\Admin\Uploads;

use Illuminate\Contracts\Filesystem\Filesystem as Disk;
use RuntimeException;

/**
 * A finished upload handed to the module it was for (§4 of the video spec).
 *
 * The session is gone by the time this exists; the `.part` file is the consumer's now, and it
 * either moves it somewhere (`moveTo`) or throws it away (`discard`). One it forgets about is
 * swept by `webx:prune-uploads` once it is older than the sessions' TTL.
 */
final readonly class ClaimedUpload
{
    public function __construct(
        public string $id,
        public string $path,
        public string $name,
        public int $size,
        public string $type,
    ) {}

    /**
     * Stream the file onto a disk and delete the piece file — a copy, not a rename, because the
     * destination is usually not the local disk, and a stream because it may be gigabytes.
     */
    public function moveTo(Disk $disk, string $path): void
    {
        $stream = fopen($this->path, 'rb');

        if ($stream === false) {
            throw new RuntimeException("The upload [{$this->id}] is no longer on the disk.");
        }

        try {
            if ($disk->writeStream($path, $stream) === false) {
                throw new RuntimeException("The upload [{$this->id}] could not be written to [{$path}].");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->discard();
    }

    public function discard(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
