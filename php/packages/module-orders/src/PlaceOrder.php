<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use WebxUi\Cart\Models\Cart;

/**
 * What `Orders::place()` makes an order of (spec §6.1). The checkout fills it from its form; code
 * of a site or a test fills it by hand.
 */
final readonly class PlaceOrder
{
    /**
     * @param  int|null  $expectedPayable  what the buyer saw; null — no check (code, tests)
     * @param  bool  $awaitPayment  the chosen payment is online (§5.1): the checkout knows the
     *                              method, the order does not (decision 10)
     * @param  array<string, array<string, mixed>>  $blocks  'delivery' => DeliverySnapshot->toArray(), 'payment' => […]
     * @param  string|null  $idempotencyKey  the checkout's form token; stored hashed
     * @param  int|null  $userId  the signed-in visitor; the caller passes it, so a command or a
     *                            test does not depend on a request
     */
    public function __construct(
        public Cart $cart,
        public string $locale,
        public Contact $customer,
        public ?Contact $recipient = null,
        public ?string $comment = null,
        public ?int $expectedPayable = null,
        public bool $awaitPayment = false,
        public array $blocks = [],
        public ?string $idempotencyKey = null,
        public ?int $userId = null,
        public string $source = 'checkout',
    ) {}
}
