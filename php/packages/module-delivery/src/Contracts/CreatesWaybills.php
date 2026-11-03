<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Contracts;

use WebxUi\Delivery\Shipping\Label;
use WebxUi\Delivery\Shipping\Shipment;
use WebxUi\Delivery\Shipping\Waybill;
use WebxUi\Delivery\Shipping\WaybillRefused;
use WebxUi\Delivery\Shipping\WaybillRequest;
use WebxUi\Delivery\Sync\CarrierUnavailable;

/**
 * A method type that can register a shipment at its carrier (§11.2). Nothing calls it by
 * itself: a manager presses «Create waybill» on the order's tab, or an agent asks for one —
 * there is no waybill made on `OrderPlaced` in v1 (decision 34).
 */
interface CreatesWaybills
{
    /**
     * @throws CarrierUnavailable the carrier did not answer; nothing is written
     * @throws WaybillRefused the carrier said no, with its reason in words
     */
    public function createWaybill(WaybillRequest $request): Waybill;

    /** The printable label or waybill, if the carrier gives one: a URL or PDF bytes. */
    public function label(Shipment $shipment): ?Label;

    /**
     * @throws CarrierUnavailable
     * @throws WaybillRefused the parcel is on its way already
     */
    public function cancelWaybill(Shipment $shipment): void;
}
