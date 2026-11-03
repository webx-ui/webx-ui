<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\Testing\CarrierABranch;
use WebxUi\Delivery\Testing\CarrierACourier;
use WebxUi\Delivery\Testing\CarrierALocker;
use WebxUi\Delivery\Testing\CarrierContract;
use WebxUi\Delivery\Testing\FakeCarrier;
use WebxUi\Delivery\Testing\FakeCarrierSite;

/**
 * The contract of a satellite (§13.3) on carrier A of a development site — what the guide of a
 * carrier walks through: three directories over several pages, namesakes and apostrophes, its three
 * types.
 */
final class CarrierContractTest extends PHPUnitTestCase
{
    use CarrierContract;

    protected function carrier(): Carrier
    {
        return new FakeCarrier(
            localities: FakeCarrierSite::LOCALITIES,
            points: FakeCarrierSite::POINTS,
            pageSize: 10,
            streets: FakeCarrierSite::STREETS,
            streetPageSize: 50,
        );
    }

    protected function methodTypes(): array
    {
        return [new CarrierABranch, new CarrierALocker, new CarrierACourier];
    }
}
