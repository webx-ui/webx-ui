<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Fakes;

use Closure;
use WebxUi\Delivery\AbstractMethodType;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\Quote;

/** `carrier-a.door`: a satellite's type priced by «the carrier's API» — whatever the test says. */
final class FakeDoorType extends AbstractMethodType
{
    /** @var (Closure(Method, DeliveryContext): Quote)|null */
    public static ?Closure $answer = null;

    /** How many times «the API» was called. */
    public static int $calls = 0;

    public function key(): string
    {
        return 'carrier-a.door';
    }

    public function asks(): Asks
    {
        return Asks::Nothing;
    }

    public function quote(Method $method, DeliveryContext $context): Quote
    {
        self::$calls++;

        return self::$answer === null ? Quote::fixed(900) : (self::$answer)($method, $context);
    }
}
