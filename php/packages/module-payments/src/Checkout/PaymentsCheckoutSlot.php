<?php

declare(strict_types=1);

namespace WebxUi\Payments\Checkout;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use LogicException;
use Throwable;
use WeakMap;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\PaymentSlot;
use WebxUi\Orders\Checkout\Slots\SlotStep;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Checkout\Steps\NamesErrors;
use WebxUi\Orders\Checkout\Steps\StepError;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Kind;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Rendering\Assets;
use WebxUi\Payments\Selections;

/**
 * Payment as the checkout's slot «payment» (spec §10; §22.3 of the orders spec): the step is
 * `webx-payments::step` — the methods offered for the cart's amount and the chosen delivery as
 * radios — and the choice is the cart's `payment_selections` row, written only by the payment API.
 * Registered by the provider when the checkout is there.
 *
 * A choice the delivery made unavailable is dropped when the step is next read, and the step says
 * so (§7.3). The order's block is the type's snapshot (§10.4).
 */
final class PaymentsCheckoutSlot implements NamesErrors, PaymentSlot
{
    /**
     * What one checkout read of the step, once a request.
     *
     * @var WeakMap<CheckoutContext, StepState>
     */
    private WeakMap $states;

    public function __construct(private readonly Methods $methods)
    {
        $this->states = new WeakMap;
    }

    public function key(): string
    {
        return CheckoutSlots::PAYMENT;
    }

    /** Something to pay, and a method for it — or methods waiting for the delivery (decision 5). */
    public function needed(CheckoutContext $context): bool
    {
        $state = $this->state($context);

        return $state->context->payable > 0 && ($state->options !== [] || $state->waiting);
    }

    public function render(CheckoutContext $context): Htmlable
    {
        $errors = view()->shared('errors');

        return new HtmlString(view('webx-payments::step', [
            'step' => $state = $this->state($context),
            'groups' => $this->methods->grouped($state->options),
            'urls' => ['selection' => route('webx-payments.selection.update')],
            'event' => SlotStep::CHANGED,
            'script' => app(Assets::class)->script(),
            'errors' => is_object($errors) ? $errors : null,
        ])->render());
    }

    public function summary(CheckoutContext $context): ?string
    {
        return $this->state($context)->chosen?->title;
    }

    public function errorCodes(): array
    {
        return [
            'payment.method' => 'no method is chosen, or the chosen one is not offered for this amount and delivery any more',
        ];
    }

    /** The same rules for both purposes: the methods are the cache's, nothing here asks a gateway. */
    public function check(CheckoutContext $context, Purpose $purpose): array
    {
        $state = $this->state($context);

        if ($state->chosen !== null) {
            return [];
        }

        $word = $state->dropped || $state->selected !== null ? 'method_gone' : 'choose_method';

        return [new StepError($this->key(), 'method', (string) trans('webx-payments::buyer.errors.'.$word, [], $context->locale), 'payment.method')];
    }

    public function snapshot(CheckoutContext $context): array
    {
        $option = $this->state($context)->chosen ?? throw new LogicException('Nothing is chosen for the payment of this cart.');

        return $option->type->snapshot($option->method, $this->paymentContext($context, Purpose::Order))->toArray();
    }

    public function awaitsPayment(CheckoutContext $context): bool
    {
        return $this->state($context)->chosen?->type->awaitsPayment() ?? false;
    }

    /**
     * An online method opens its attempt right after the order (§8.2, §10.1): a row `created`, no
     * network — the gateway is called on `/pay/go/{uuid}`, where a failure has a page of its own.
     * Anything else is «Thank you». Never throws: a failure is logged, and «Thank you» has «Pay».
     */
    public function next(OrderData $order): ?string
    {
        try {
            $model = Order::query()->find($order->id);
            $block = $model !== null && is_array($model->payment) ? $model->payment : null;

            if ($model === null || $block === null || ($block['kind'] ?? null) !== Kind::Online->value
                || ! $model->awaiting_payment || $model->cancelled_at !== null || ! is_string($block['method'] ?? null)) {
                return null;
            }

            $method = $this->methods->any($block['method']);

            if ($method === null) {
                return null;
            }

            return $this->links()->go(app(Payments::class)->begin($model, $method, 'checkout'));
        } catch (Throwable $e) {
            Log::warning('webx-payments: the attempt of a placed order was not opened', ['order' => $order->number, 'exception' => $e]);

            return null;
        }
    }

    /**
     * «Pay» on «Thank you» and the order's page (§8.4): the signed page of payment of an order that
     * still awaits its money and is not cancelled; null otherwise, or while the site has no page.
     * Money held is paid for the buyer (§8.3): the shop captures it, nothing is to pay again.
     */
    public function payAgainUrl(OrderData $order): ?string
    {
        $model = Order::query()->find($order->id);

        if ($model === null || ! $model->awaiting_payment || $model->cancelled_at !== null
            || Payment::query()->where('order_id', $model->id)->where('status', PaymentStatus::Authorized->value)->exists()) {
            return null;
        }

        return $this->links()->page($model) ?: null;
    }

    /** The context the methods are asked against for this checkout. */
    public function paymentContext(CheckoutContext $context, Purpose $purpose = Purpose::Checkout): PaymentContext
    {
        $shipping = $context->shippingChoice();

        return new PaymentContext(
            payable: $context->state->payable,
            currency: (string) ($context->state->currency ?? $context->currency ?? config('webx-cart.currency', '')),
            locale: $context->locale,
            shipping: $shipping,
            purpose: $purpose,
            shippingPending: $shipping === null && $this->deliveryStepShown($context),
        );
    }

    /** The step as the checkout and the API read it: forgotten after the API writes the choice. */
    public function state(CheckoutContext $context): StepState
    {
        if (isset($this->states[$context])) {
            return $this->states[$context];
        }

        $payment = $this->paymentContext($context);
        $options = $this->methods->available($payment);
        $cart = $context->cart?->id;
        $selected = $cart !== null ? $this->selections()->get($cart) : null;
        $chosen = null;
        $dropped = false;

        foreach ($options as $option) {
            if ($option->key() === $selected) {
                $chosen = $option;
            }
        }

        // A choice the delivery took away is dropped and the step says so (§7.3); one that only
        // waits for the delivery is kept for when the buyer has chosen it.
        if ($selected !== null && $chosen === null && $cart !== null) {
            $method = $this->methods->find($selected);
            $reason = $method !== null ? $this->methods->check($method, $payment)->reason : null;

            if ($reason !== 'delivery_pending') {
                $this->selections()->forget($cart);
                $dropped = $method !== null && $reason === 'delivery_rule';
                $selected = null;
            }
        }

        return $this->states[$context] = new StepState(
            context: $payment,
            options: $options,
            chosen: $chosen,
            selected: $selected,
            dropped: $dropped,
            waiting: $this->methods->waitingForDelivery($payment),
        );
    }

    /** Read the step again on the next ask: after the API wrote the choice. */
    public function forget(CheckoutContext $context): void
    {
        unset($this->states[$context]);
    }

    /** Whether the checkout shows a delivery step for this cart: then «not chosen» is «not yet». */
    private function deliveryStepShown(CheckoutContext $context): bool
    {
        try {
            $step = app(CheckoutSteps::class)->find(CheckoutSlots::DELIVERY, $context);

            return $step instanceof SlotStep && $step->applies($context);
        } catch (Throwable) {
            return false;
        }
    }

    private function links(): PaymentLinks
    {
        return app(PaymentLinks::class);
    }

    private function selections(): Selections
    {
        return app(Selections::class);
    }
}
