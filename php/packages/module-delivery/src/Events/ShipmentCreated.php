<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use WebxUi\Delivery\Shipping\Shipment;

/** A waybill was made at the carrier (§11.2): by the manager's button (`panel`) or an agent (`mcp`). */
final readonly class ShipmentCreated implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Shipment $shipment,
        public string $source,
    ) {}
}
