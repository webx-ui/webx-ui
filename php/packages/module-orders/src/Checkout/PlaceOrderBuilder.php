<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use LogicException;
use WebxUi\Orders\Contact;
use WebxUi\Orders\PlaceOrder;

/**
 * The input of an order as the steps put it together (spec §20.2): each step sets what it owns —
 * the buyer, the recipient, the comment, its block, whether the order awaits payment — and reads
 * nobody else's. A block whose key is taken is a mistake of the project, not of the buyer.
 */
final class PlaceOrderBuilder
{
    private ?Contact $customer = null;

    private ?Contact $recipient = null;

    private ?string $comment = null;

    private bool $awaitPayment = false;

    /** @var array<string, array<string, mixed>> */
    private array $blocks = [];

    public function customer(Contact $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    /** Null — the buyer receives the order themselves. */
    public function recipient(?Contact $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function comment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /** The chosen payment is online: the order comes in awaiting it (§5.1). */
    public function awaitPayment(bool $await = true): self
    {
        $this->awaitPayment = $await;

        return $this;
    }

    /** @param  array<string, mixed>  $block */
    public function block(string $key, array $block): self
    {
        if (array_key_exists($key, $this->blocks)) {
            throw new LogicException("Two steps of the checkout put the block \"{$key}\" into one order.");
        }

        $this->blocks[$key] = $block;

        return $this;
    }

    /** Whether a step put the buyer in: without one there is no order to place. */
    public function hasCustomer(): bool
    {
        return $this->customer !== null;
    }

    public function build(
        CheckoutContext $context,
        ?int $expectedPayable,
        ?string $idempotencyKey = null,
    ): PlaceOrder {
        if ($this->customer === null || $context->cart === null) {
            throw new LogicException('An order of the checkout needs a cart and a step that puts the buyer in.');
        }

        return new PlaceOrder(
            cart: $context->cart,
            locale: $context->locale,
            customer: $this->customer,
            recipient: $this->recipient,
            comment: $this->comment,
            expectedPayable: $expectedPayable,
            awaitPayment: $this->awaitPayment,
            blocks: $this->blocks,
            idempotencyKey: $idempotencyKey,
            userId: $context->visitor?->id,
            source: 'checkout',
        );
    }
}
