<?php

declare(strict_types=1);

namespace WebxUi\Admin\Uploads;

/**
 * How much room is left where the pieces are written. A class of its own so that a test can say
 * "the disk is full" without filling one.
 */
class FreeSpace
{
    /** Bytes free under the path, or null when the system will not say. */
    public function bytes(string $path): ?int
    {
        $free = @disk_free_space($path);

        return $free === false ? null : (int) $free;
    }
}
