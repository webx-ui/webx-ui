<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Types;

use WebxUi\Delivery\AbstractMethodType;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\Method;

/**
 * `store.courier` — the shop's own courier to the door (§10.2): only in the localities the shop
 * turned it on in, none — nowhere; one fixed price of the method in all of them. The street is
 * free text (decision 31).
 */
final class StoreCourier extends AbstractMethodType
{
    public const string KEY = 'store.courier';

    public function key(): string
    {
        return self::KEY;
    }

    public function asks(): Asks
    {
        return Asks::Address;
    }

    public function available(Method $method, DeliveryContext $context): bool
    {
        return $method->goesTo($context->locality->id);
    }
}
