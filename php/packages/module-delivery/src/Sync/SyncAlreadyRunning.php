<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Sync;

use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\Models\DeliverySyncRun;

/** A run of the directory is going on: a second one does not start (§7.7). */
final class SyncAlreadyRunning extends DeliveryException
{
    public function __construct(public readonly DeliverySyncRun $run)
    {
        parent::__construct((string) __('webx-delivery::delivery.sync.running', ['page' => $run->pages + 1]));
    }
}
