<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Events;

/**
 * The amount the buyer saw is not what the order comes to (spec §24.4, §30): `from` was on the
 * page, `to` is the count of the confirmation. Each divergence once — a press that diverges twice
 * is two. The draft counts them for the funnel (`orders_checkout_funnel`).
 */
final readonly class CheckoutPayableChanged
{
    public function __construct(
        public int $cartId,
        public int $from,
        public int $to,
    ) {}
}
