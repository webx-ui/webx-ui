<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Types;

use WebxUi\Delivery\AbstractMethodType;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\PointKinds;

/**
 * `store.pickup` — the buyer picks the order up at a point of the shop (§10.1). The kind of point
 * is the method's: a store and a pick-up point are two methods with a title, an icon and a price
 * of their own, and so is any kind the shop names itself (decision 9).
 *
 * Offered where the locality has an active point of the method's kind — the base counts that
 * before the type is asked (§6.3); the price is the method's, none — «Free».
 */
final class StorePickup extends AbstractMethodType
{
    public const string KEY = 'store.pickup';

    public function key(): string
    {
        return self::KEY;
    }

    public function asks(): Asks
    {
        return Asks::Point;
    }

    /** The built-in kinds of the shop; a kind the shop names itself is a method's `point_kind` too. */
    public function pointKinds(): array
    {
        return PointKinds::STORE_KINDS;
    }
}
