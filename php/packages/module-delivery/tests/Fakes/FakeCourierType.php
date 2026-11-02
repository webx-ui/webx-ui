<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Fakes;

use WebxUi\Delivery\AbstractMethodType;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Contracts\CreatesWaybills;
use WebxUi\Delivery\Contracts\TracksShipments;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\Quote;
use WebxUi\Delivery\Testing\ShipsByFakeParcels;

/** `carrier-a.courier`: to the door, the street from the carrier's directory. */
final class FakeCourierType extends AbstractMethodType implements CreatesWaybills, TracksShipments
{
    use ShipsByFakeParcels;

    /** What the type was given the last time it was asked: the carrier's own id of the locality. */
    public static ?string $lastCarrierLocality = null;

    public function key(): string
    {
        return 'carrier-a.courier';
    }

    public function asks(): Asks
    {
        return Asks::Address;
    }

    public function quote(Method $method, DeliveryContext $context): Quote
    {
        self::$lastCarrierLocality = $context->carrierLocality;

        return $context->cart->subtotal > 100000 ? Quote::unavailable('Too heavy for a courier') : Quote::carrierRates();
    }
}
