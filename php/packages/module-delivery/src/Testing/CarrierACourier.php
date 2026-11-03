<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Testing;

use WebxUi\Delivery\AbstractMethodType;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Contracts\CreatesWaybills;
use WebxUi\Delivery\Contracts\TracksShipments;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\Quote;

/**
 * `carrier-a.courier` of the fake carrier on a development site ({@see FakeCarrierSite}): to the
 * door, the street only from carrier A's directory of the town (§8.2) — a town the carrier gives
 * no streets for has the method switched off with a hint.
 */
final class CarrierACourier extends AbstractMethodType implements CreatesWaybills, TracksShipments
{
    use ShipsByFakeParcels;

    public const string KEY = 'carrier-a.courier';

    public function key(): string
    {
        return self::KEY;
    }

    public function asks(): Asks
    {
        return Asks::Address;
    }

    public function countries(): array
    {
        return ['XX'];
    }

    public function quote(Method $method, DeliveryContext $context): Quote
    {
        return $method->price === null ? Quote::carrierRates() : Quote::fixed($method->price);
    }
}
