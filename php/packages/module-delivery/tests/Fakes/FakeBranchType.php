<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Fakes;

use WebxUi\Delivery\AbstractMethodType;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Contracts\CreatesWaybills;
use WebxUi\Delivery\Contracts\DescribesWaybill;
use WebxUi\Delivery\Contracts\TracksShipments;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\Quote;
use WebxUi\Delivery\Testing\ShipsByFakeParcels;

/**
 * `carrier-a.branch`: pick up at a branch; the buyer pays the carrier unless a price is set. Its
 * waybill's own fields and cash on delivery are what a test sets — none and yes, as the base's
 * dialog without `DescribesWaybill`, until it says otherwise.
 */
final class FakeBranchType extends AbstractMethodType implements CreatesWaybills, DescribesWaybill, TracksShipments
{
    use ShipsByFakeParcels;

    /** @var list<array<string, mixed>> */
    public static array $fields = [];

    public static bool $cod = true;

    public static function reset(): void
    {
        self::$fields = [];
        self::$cod = true;
    }

    public function waybillFields(): array
    {
        return self::$fields;
    }

    public function supportsCod(): bool
    {
        return self::$cod;
    }

    public function key(): string
    {
        return 'carrier-a.branch';
    }

    public function asks(): Asks
    {
        return Asks::Point;
    }

    public function pointKinds(): array
    {
        return ['branch'];
    }

    public function countries(): array
    {
        return ['XX'];
    }

    public function quote(Method $method, DeliveryContext $context): Quote
    {
        return $method->price === null ? Quote::carrierRates(7000) : Quote::fixed($method->price);
    }
}
