<?php

declare(strict_types=1);

namespace WebxUi\Payments\Contracts;

use WebxUi\Payments\Availability;
use WebxUi\Payments\Kind;
use WebxUi\Payments\Method;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\PaymentSnapshot;
use WebxUi\Payments\Presentation;

/**
 * One way to pay: on receipt, by bank transfer, a gateway's hosted page (spec §6.1).
 * The site turns a type into methods; a buyer sees methods.
 *
 * A type never touches the database or a model: the base hands it an immutable `Method`. New
 * abilities come only as optional interfaces on top of this one, so that an old satellite keeps
 * working when the base learns something new.
 */
interface MethodType
{
    /** 'store.on_receipt', 'gateway-a.card' — <provider>.<kind>, stable forever. */
    public function key(): string;

    /** 'store' for the built-in ones, or a satellite's gateway key (§14.1). */
    public function provider(): string;

    /** Offline — the manager marks payment; Online — the gateway confirms it (§6.3). */
    public function kind(): Kind;

    /** Whether the order waits for payment once placed (§11.1): online and bank transfer — yes. */
    public function awaitsPayment(): bool;

    /**
     * Fields of payment_methods.settings this type reads, in the shape the panel draws (§13.3).
     *
     * @return list<array<string, mixed>>
     */
    public function settings(): array;

    /**
     * Currencies the type can take; null — any. ISO 4217.
     *
     * @return list<string>|null
     */
    public function currencies(): ?array;

    /** Cheap, no gateway call: type-level limits beyond the method's own (§7). */
    public function available(Method $method, PaymentContext $context): Availability;

    /**
     * What the buyer reads for this method in this context: its title and the line under it.
     * «Pay at the counter» instead of «Payment on receipt» at the shop's own point (§7.3, §12.1).
     */
    public function present(Method $method, PaymentContext $context): Presentation;

    /** The `payment` block of the order (§10.4): common part plus the type's own details. */
    public function snapshot(Method $method, PaymentContext $context): PaymentSnapshot;
}
