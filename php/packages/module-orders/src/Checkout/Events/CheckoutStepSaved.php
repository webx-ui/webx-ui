<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Events;

/**
 * A step of the checkout was kept in the draft (spec §30): for the funnel and a future companion
 * of the carts left behind. After the draft is saved; a bot's step, answered as if it worked, is
 * not one.
 */
final readonly class CheckoutStepSaved
{
    public function __construct(
        public int $cartId,
        public string $step,
    ) {}
}
