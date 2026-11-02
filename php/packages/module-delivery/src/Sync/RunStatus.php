<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

/** A run of the journal (§5 `delivery_sync_runs`). */
enum RunStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * The pass was complete, but it would remove more than the threshold allows: nothing was
     * removed, a person confirms (§7.5).
     */
    case Held = 'held';
}
