<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Testing;

use WebxUi\Delivery\Shipping\Label;
use WebxUi\Delivery\Shipping\Shipment;
use WebxUi\Delivery\Shipping\TrackingState;
use WebxUi\Delivery\Shipping\Waybill;
use WebxUi\Delivery\Shipping\WaybillRequest;

/**
 * `CreatesWaybills` and `TracksShipments` of a method type of «carrier A», answered by the test's
 * {@see FakeParcels}. A type of a test uses it and declares both interfaces.
 */
trait ShipsByFakeParcels
{
    public function createWaybill(WaybillRequest $request): Waybill
    {
        return app(FakeParcels::class)->createWaybill($request);
    }

    public function label(Shipment $shipment): ?Label
    {
        return app(FakeParcels::class)->label($shipment);
    }

    public function cancelWaybill(Shipment $shipment): void
    {
        app(FakeParcels::class)->cancelWaybill($shipment);
    }

    /**
     * @param  list<Shipment>  $shipments
     * @return array<int, TrackingState>
     */
    public function track(array $shipments): array
    {
        return app(FakeParcels::class)->track($shipments);
    }
}
