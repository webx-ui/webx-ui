<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

/** Who started a run (§5 `delivery_sync_runs.trigger`). */
enum Trigger: string
{
    case Schedule = 'schedule';
    case Panel = 'panel';
    case Mcp = 'mcp';
    case Command = 'command';
}
