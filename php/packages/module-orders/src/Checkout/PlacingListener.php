<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Events\OrderPlacing;
use WebxUi\Orders\Orders;

/**
 * What the checkout does inside the transaction of its own order (spec §21.2, §23.4):
 *
 * - the agreement with the terms goes into the order's history;
 * - the draft keeps the contacts and the recipient — the same guest's next order fills them — and
 *   forgets the rest: the comment, the steps, the agreement, the key, the amount shown;
 * - its last step is the confirmation: the funnel counts the drafts that came to an order (§32).
 *
 * Only for the order the confirmation is placing now — `expect()` says which draft it is. An order
 * from the panel, an agent or the site's code is none of the checkout's, whatever its `source`:
 * `PlaceOrder`'s default is `checkout` too. One a request (scoped), so the draft passes from the
 * controller to the listener without being read again.
 */
final class PlacingListener
{
    public const string SOURCE = 'checkout';

    private ?CheckoutDraft $draft = null;

    public function __construct(
        private readonly CheckoutTerms $terms,
        private readonly Orders $orders,
    ) {}

    /** The draft of the order about to be placed by the confirmation. */
    public function expect(?CheckoutDraft $draft): void
    {
        $this->draft = $draft;
    }

    public function handle(OrderPlacing $event): void
    {
        $draft = $this->draft;

        if ($draft === null || $event->source !== self::SOURCE || $event->order->cart_id !== $draft->cart_id) {
            return;
        }

        $this->draft = null;
        $this->terms->record($event->order, $draft, $this->orders, $event->at);

        $draft->fill([
            'comment' => null,
            'steps' => null,
            'terms_accepted_at' => null,
            'terms_version' => null,
            'place_key' => null,
            'shown_payable' => null,
            'last_step' => CheckoutSteps::CONFIRM,
        ])->save();
    }
}
