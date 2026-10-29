<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * What a manager makes an order of in the panel or through the agent's tools (spec §29.2): the
 * lines as «Add to cart» takes them, the buyer, and nothing of the price — the order counts it the
 * way the checkout does (`Orders::placeByManager()`).
 */
final readonly class ManagerOrder
{
    /**
     * @param  list<array<string, mixed>>  $lines  each { type, ref, qty?, options? }, as `CartService::add()` takes them
     * @param  string|null  $idempotencyKey  the form's token: a second press is the order of the first
     */
    public function __construct(
        public array $lines,
        public string $locale,
        public Contact $customer,
        public ?Contact $recipient = null,
        public ?string $comment = null,
        public ?string $idempotencyKey = null,
    ) {}
}
