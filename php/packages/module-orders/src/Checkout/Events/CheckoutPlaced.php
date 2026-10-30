<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Events;

/**
 * The checkout placed an order (spec §30), after the commit: for analytics. What concerns the order
 * itself listens to `OrderPlaced`, which comes from every source — this one only from the page.
 */
final readonly class CheckoutPlaced
{
    public function __construct(
        public int $orderId,
        public int $cartId,
    ) {}
}
