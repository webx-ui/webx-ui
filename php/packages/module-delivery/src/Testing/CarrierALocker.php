<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Testing;

use WebxUi\Delivery\AbstractMethodType;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Contracts\CreatesWaybills;
use WebxUi\Delivery\Contracts\DescribesWaybill;
use WebxUi\Delivery\Contracts\TracksShipments;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\Quote;

/**
 * `carrier-a.locker` of the fake carrier on a development site ({@see FakeCarrierSite}): the buyer
 * picks the parcel up at a locker of carrier A and pays the carrier, unless the method has a price.
 *
 * A locker takes no cash on delivery: its waybill asks the weight and what is inside, and no cash.
 */
final class CarrierALocker extends AbstractMethodType implements CreatesWaybills, DescribesWaybill, TracksShipments
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

    public function waybillFields(): array
    {
        return [
            ['name' => 'weight', 'type' => 'number', 'required' => true, 'default' => 0.5, 'min' => 0.1, 'step' => 0.1, 'label' => ['en' => 'Weight, kg', 'ru' => 'Вес, кг']],
            ['name' => 'description', 'type' => 'text', 'default' => 'Goods', 'label' => ['en' => 'What is inside', 'ru' => 'Что внутри']],
        ];
    }

    public function supportsCod(): bool
    {
        return false;
    }
}
