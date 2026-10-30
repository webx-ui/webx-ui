<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Slots;

use Illuminate\Contracts\Support\Htmlable;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Steps\StepError;

/**
 * A step that belongs to another module (spec §22.1): the checkout knows it by its key and this
 * contract alone and wraps it into a step itself (`SlotStep`). The owner keeps the buyer's choice
 * by the cart, prints its own components and talks to its own API; after each change of the
 * choice it dispatches `webx:checkout:slot-changed` on `document` (`SlotStep::CHANGED`).
 *
 * The owner registers it in boot(), only when `module-orders` is installed:
 * `if (class_exists(CheckoutSlots::class)) $this->callAfterResolving(CheckoutSlots::class, fn ($s) => $s->register(...))`.
 */
interface Slot
{
    /** 'delivery' | 'payment' — the step key and the order block key at once. */
    public function key(): string;

    /** Whether the step is shown: delivery — Delivery::needed($state), payment — payable > 0 and a method available. */
    public function needed(CheckoutContext $context): bool;

    /** The step's body; the slot prints its own components and talks to its own API. */
    public function render(CheckoutContext $context): Htmlable;

    /** One line for the collapsed step: "Branch 12, Town D · Free". Null — nothing chosen yet. */
    public function summary(CheckoutContext $context): ?string;

    /**
     * Whether what the buyer chose is still valid.
     * Purpose::Checkout — from stored records only; Purpose::Order — strict: re-validate,
     * re-quote without cache, no fallbacks (§9.2 of the delivery spec).
     *
     * @return list<StepError>
     */
    public function check(CheckoutContext $context, Purpose $purpose): array;

    /**
     * The order block (§6.4) — called after check(Order) passed.
     *
     * @return array<string, mixed>
     */
    public function snapshot(CheckoutContext $context): array;
}
