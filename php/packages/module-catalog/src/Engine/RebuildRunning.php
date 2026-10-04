<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Engine;

use RuntimeException;

/**
 * A rebuild refused because another one holds the lock ({@see Indexer::LOCK}) — started from the
 * console or from the panel, it does not matter which: both would recreate the same tables.
 */
final class RebuildRunning extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Another rebuild of the catalogue index is running: wait for it to end, then start this one again.');
    }
}
