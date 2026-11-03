<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Contracts;

use WebxUi\Delivery\Shipping\Shipment;
use WebxUi\Delivery\Shipping\TrackingState;
use WebxUi\Delivery\Sync\CarrierUnavailable;

/**
 * A method type whose carrier says where a parcel is (§11.3). The base asks on its schedule
 * (`webx:delivery:track`), a batch at a time — carriers track by many numbers at once; in v1
 * there is no webhook (decision 39).
 */
interface TracksShipments
{
    /**
     * The current state of these shipments, translated into the base's statuses.
     *
     * @param  list<Shipment>  $shipments
     * @return array<int, TrackingState> shipment id → state; one left out is unknown — asked again later
     *
     * @throws CarrierUnavailable
     */
    public function track(array $shipments): array;
}
