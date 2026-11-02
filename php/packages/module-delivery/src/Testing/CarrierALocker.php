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
 * `carrier-a.locker` of the fake carrier on a development site ({@see FakeCarrierSite}): the buyer
 * picks the parcel up at a locker of carrier A and pays the carrier, unless the method has a price.
 */
final class CarrierALocker extends AbstractMethodType implements CreatesWaybills, TracksShipments
{
    use ShipsByFakeParcels;

    public const string KEY = 'carrier-a.locker';

    public function key(): string
    {
        return self::KEY;
    }

    public function asks(): Asks
    {
        return Asks::Point;
    }

    public function pointKinds(): array
    {
        return ['locker'];
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
