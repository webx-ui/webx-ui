<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Contracts;

use WebxUi\Delivery\Asks;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\Quote;
use WebxUi\Delivery\Selection;
use WebxUi\Delivery\SelectionInput;

/**
 * One kind of delivery a carrier offers: pick up at a branch, at a locker, a courier.
 * The site turns a type into methods; a buyer sees methods. Asked per request with a context.
 *
 * New methods only ever come as optional interfaces on top of this one (`CreatesWaybills`,
 * `TracksShipments`), so that an old satellite never breaks.
 */
interface MethodType
{
    /** 'store.pickup', 'carrier-a.locker' — <carrier>.<kind>, stable forever. */
    public function key(): string;

    /** The carrier this type belongs to: 'store' or a satellite's carrier key. */
    public function carrier(): string;

    /** What the buyer is asked once the method is chosen. */
    public function asks(): Asks;

    /**
     * Point kinds a method of this type may take ('branch', 'locker'); empty unless Asks::Point.
     *
     * @return list<string>
     */
    public function pointKinds(): array;

    /**
     * ISO 3166-1 codes the type can serve; null — any country the carrier has localities in.
     *
     * @return list<string>|null
     */
    public function countries(): ?array;

    /**
     * Fields of `delivery_methods.settings` this type reads, in the shape the panel draws.
     *
     * @return list<array<string, mixed>>
     */
    public function settings(): array;

    /**
     * Whether this method serves the chosen locality for this cart. Cheap: no carrier API.
     * Methods asking for a point are already filtered by point presence before this (§6.3).
     */
    public function available(Method $method, DeliveryContext $context): bool;

    /** The price for this cart and locality (§9.1). May call the carrier's API — cached, §9.2. */
    public function quote(Method $method, DeliveryContext $context): Quote;

    /**
     * Checks what the buyer entered and resolves it to stored records.
     *
     * @return Selection|array<string, string> messages keyed by field name on failure
     */
    public function validate(Method $method, DeliveryContext $context, SelectionInput $input): Selection|array;
}
