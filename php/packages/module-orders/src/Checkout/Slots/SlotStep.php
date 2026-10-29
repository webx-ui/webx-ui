<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Slots;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;
use WeakMap;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\PlaceOrderBuilder;
use WebxUi\Orders\Checkout\Steps\NamesErrors;
use WebxUi\Orders\Checkout\Steps\Step;
use WebxUi\Orders\Checkout\Steps\StepError;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Models\Order;

/**
 * A slot as a step of the checkout (spec §22.1). A slot that throws does not take the page with
 * it: while the buyer looks, its section says it could not be loaded and the error goes to the
 * log; on the confirmation the order is not placed — like a source of the cart's chain.
 */
final class SlotStep implements NamesErrors, Step
{
    /** What a slot dispatches on `document` after each change of the choice; detail: slot, summary. */
    public const string CHANGED = 'webx:checkout:slot-changed';

    /**
     * Whether the slot is needed, once a request: null — needed() threw.
     *
     * @var WeakMap<CheckoutContext, bool|null>
     */
    private WeakMap $needed;

    public function __construct(
        private readonly Slot $slot,
        private readonly Views $views,
    ) {
        $this->needed = new WeakMap;
    }

    public function slot(): Slot
    {
        return $this->slot;
    }

    public function key(): string
    {
        return $this->slot->key();
    }

    public function title(): string
    {
        return (string) __('webx-orders::checkout.steps.'.$this->slot->key());
    }

    /** A slot that failed to answer is shown: its section tells the buyer, the confirmation stops. */
    public function applies(CheckoutContext $context): bool
    {
        if (! $this->needed->offsetExists($context)) {
            try {
                $this->needed[$context] = $this->slot->needed($context);
            } catch (Throwable $e) {
                $this->failed('needed', $e);
                $this->needed[$context] = null;
            }
        }

        return $this->needed[$context] ?? true;
    }

    public function render(CheckoutContext $context): Htmlable
    {
        if ($this->broken($context)) {
            return $this->failure();
        }

        try {
            // Rendered here, so a view that throws halfway fails the section and not the page.
            return new HtmlString($this->slot->render($context)->toHtml());
        } catch (Throwable $e) {
            $this->failed('render', $e);

            return $this->failure();
        }
    }

    public function summary(CheckoutContext $context): ?string
    {
        if ($this->broken($context)) {
            return null;
        }

        try {
            return $this->slot->summary($context);
        } catch (Throwable $e) {
            $this->failed('summary', $e);

            return null;
        }
    }

    /** The failure of the slot itself, and the codes the slot names, if it does. */
    public function errorCodes(): array
    {
        return [
            $this->slot->key().'.failed' => 'the slot failed to answer: its module logged why',
            ...($this->slot instanceof NamesErrors ? $this->slot->errorCodes() : []),
        ];
    }

    /** The slot keeps the buyer's choice in its own table by the cart and through its own API. */
    public function save(CheckoutContext $context, array $input, CheckoutDraft $draft): void {}

    public function check(CheckoutContext $context, Purpose $purpose): array
    {
        if ($this->broken($context)) {
            return [$this->error()];
        }

        try {
            return $this->slot->check($context, $purpose);
        } catch (Throwable $e) {
            $this->failed('check', $e);

            return [$this->error()];
        }
    }

    /**
     * The order block of the slot and, for payment, whether the order awaits it.
     *
     * @throws ValidationException the slot failed: no order
     */
    public function contribute(CheckoutContext $context, PlaceOrderBuilder $order): void
    {
        try {
            $block = $this->slot->snapshot($context);
            $await = $this->slot instanceof PaymentSlot && $this->slot->awaitsPayment($context);
        } catch (Throwable $e) {
            $this->failed('snapshot', $e);

            throw ValidationException::withMessages([$this->slot->key() => [$this->error()->message]]);
        }

        $order->block($this->slot->key(), $block);

        if ($this->slot instanceof PaymentSlot) {
            $order->awaitPayment($await);
        }
    }

    /** The delivery slot when it is shown for this checkout and answered; otherwise null. */
    public function shipping(CheckoutContext $context): ?ShippingSlot
    {
        return $this->slot instanceof ShippingSlot && $this->applies($context) && ! $this->broken($context)
            ? $this->slot
            : null;
    }

    /** The chosen delivery for the payment step; a slot that throws chose nothing. */
    public function choice(CheckoutContext $context): ?ShippingChoice
    {
        $slot = $this->shipping($context);

        try {
            return $slot?->choice($context);
        } catch (Throwable $e) {
            $this->failed('choice', $e);

            return null;
        }
    }

    /** The line «Delivery cost»; a slot that throws has nothing to say there. */
    public function totalsLine(CheckoutContext $context): ?ShippingLine
    {
        $slot = $this->shipping($context);

        try {
            return $slot?->totalsLine($context);
        } catch (Throwable $e) {
            $this->failed('totalsLine', $e);

            return null;
        }
    }

    /**
     * Where the buyer goes after the order: the gateway, or null — «Thank you». The order is placed
     * already, so a slot that throws sends the buyer to «Thank you», where «Pay» waits (§22.3).
     */
    public function next(Order $order): ?string
    {
        if (! $this->slot instanceof PaymentSlot) {
            return null;
        }

        try {
            return $this->slot->next(OrderData::of($order));
        } catch (Throwable $e) {
            $this->failed('next', $e);

            return null;
        }
    }

    private function broken(CheckoutContext $context): bool
    {
        $this->applies($context);

        return $this->needed[$context] === null;
    }

    private function error(): StepError
    {
        $key = $this->slot->key();

        return new StepError($key, null, (string) __('webx-orders::checkout.errors.slot-failed'), $key.'.failed');
    }

    private function failure(): Htmlable
    {
        return new HtmlString($this->views->make('webx-orders::checkout.steps.failed')->render());
    }

    private function failed(string $call, Throwable $e): void
    {
        Log::warning("webx-orders: the checkout's slot «{$this->slot->key()}» failed in {$call}()", [
            'slot' => $this->slot::class,
            'exception' => $e,
        ]);
    }
}
