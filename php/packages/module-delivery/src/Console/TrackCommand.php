<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Console;

use Illuminate\Console\Command;
use WebxUi\Delivery\Shipping\Shipments;

/**
 * `webx:delivery:track` (§11.3): asks the carriers about every parcel that is due — not final, its
 * time come. The schedule runs it every 30 minutes; in v1 nothing else tells the site where a
 * parcel is (no webhooks, decision 39).
 */
final class TrackCommand extends Command
{
    protected $signature = 'webx:delivery:track';

    protected $description = 'Ask the delivery carriers where the parcels of orders are now';

    public function handle(Shipments $shipments): int
    {
        $changed = $shipments->track();

        $this->components->info("Parcels that moved: {$changed}.");

        return self::SUCCESS;
    }
}
