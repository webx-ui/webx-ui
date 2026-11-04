<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\Slots\ShippingChoice;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\PaymentContext;

/**
 * What the methods are asked against for a placed order (spec §8.5): what it owes now and the
 * delivery it was placed with, so a rule by delivery answers as it did in the checkout. Payments
 * does not know the delivery module: it reads the order's block, and a carrier's cash on delivery
 * is not in it — the checkout's slot does not say it either.
 */
final class OrderContext
{
    /** The type of a pick-up at the shop's own point, as the delivery block keeps it. */
    private const string STORE_PICKUP = 'store.pickup';

    private const string STORE = 'store';

    public static function of(Order $order, int $due): PaymentContext
    {
        return new PaymentContext($due, (string) $order->currency, (string) $order->locale, self::shipping($order), Purpose::Order);
    }

    private static function shipping(Order $order): ?ShippingChoice
    {
        $block = is_array($order->delivery) ? $order->delivery : [];
        $method = $block['method'] ?? null;

        if (! is_string($method) || $method === '') {
            return null;
        }

        $carrier = is_string($block['carrier'] ?? null) && $block['carrier'] !== '' ? $block['carrier'] : self::STORE;

        return new ShippingChoice($method, $carrier, false, ($block['type'] ?? null) === self::STORE_PICKUP && $carrier === self::STORE);
    }
}
