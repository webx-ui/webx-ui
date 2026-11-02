<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Fakes;

use WebxUi\Delivery\AbstractMethodType;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\Quote;

/** `carrier-a.branch`: pick up at a branch; the buyer pays the carrier unless a price is set. */
final class FakeBranchType extends AbstractMethodType
{
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
