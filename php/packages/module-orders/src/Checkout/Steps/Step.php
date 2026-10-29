<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Steps;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\PlaceOrderBuilder;

/**
 * A section of the checkout page (spec §20.2): the contacts and the comment are the checkout's,
 * delivery and payment are slots of their modules, a project adds its own between them by
 * position. The page prints the form and the one-line summary of every step; the theme decides
 * which of them shows (§20.1).
 */
interface Step
{
    /** 'contacts', 'delivery', 'payment', 'comment' — stable, used in addresses and MCP. */
    public function key(): string;

    /** The heading of the section, in the language of the request. */
    public function title(): string;

    /** Whether the step is shown for this checkout at all (delivery: shippable lines exist). */
    public function applies(CheckoutContext $context): bool;

    /** The open step: a Blade view the page prints inside the step's section. */
    public function render(CheckoutContext $context): Htmlable;

    /** One line for the collapsed step; null — the step is not filled yet. */
    public function summary(CheckoutContext $context): ?string;

    /**
     * Keeps what the buyer sent with the step's form (`POST /checkout/steps/{key}`) in the draft;
     * the caller saves the draft. A step that keeps its choice elsewhere — a slot in its own
     * table — leaves the draft alone.
     *
     * @param  array<string, mixed>  $input  the request's fields
     *
     * @throws ValidationException errors by the names of the step's fields
     */
    public function save(CheckoutContext $context, array $input, CheckoutDraft $draft): void;

    /**
     * Whether the step is ready to place an order with. Cheap for Purpose::Checkout,
     * strict for Purpose::Order (a slot re-validates its point, re-quotes, §22).
     *
     * @return list<StepError> empty — ready
     */
    public function check(CheckoutContext $context, Purpose $purpose): array;

    /** Puts what the step owns into the order input; called once, inside place (§24.1). */
    public function contribute(CheckoutContext $context, PlaceOrderBuilder $order): void;
}
