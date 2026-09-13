<?php

declare(strict_types=1);

namespace WebxUi\Media\Images;

use RuntimeException;

/** A row whose bytes are gone — a disk emptied by hand, or a restore that missed them. */
final class MissingSource extends RuntimeException
{
    public function __construct(string $path)
    {
        parent::__construct("The file [{$path}] is not on the disk.");
    }
}
