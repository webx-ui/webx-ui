<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\CartNotOrderable;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Orders;

/**
 * The confirmation of a cart tried without placing it (spec §32, `orders_checkout_preview`): the
 * questions `POST /checkout/place` asks, in its order and with its words, and nothing written.
 *
 * 1. Every step checked for the order — what is missing is the 422 of the page, by field.
 * 2. The cart counted for the order: lines that cannot be bought — the 409 `cart_not_orderable`.
 * 3. That count against the amount the page last showed the buyer — the draft remembers it, and it
 *    is what the confirmation sends: more — the 409 `payable_changed`; less — the order with the
 *    smaller amount, as the page places it (§24.4). No page shown yet — the amount it would show.
 *
 * The antispam, the key of a repeat and the ticked box of the terms are the request's, not the
 * cart's: the answer says when the box is asked for and leaves them out.
 */
final readonly class CheckoutPreview
{
    /** An order would be placed. */
    public const string ORDER = 'order';

    /** A step lacks something: the page answers 422 with these errors. */
    public const string INVALID = 'invalid';

    /** No order, a 409 with a code (§24.2). */
    public const string CONFLICT = 'conflict';

    /** Nothing to order: the page sends the buyer back to it. */
    public const string EMPTY = 'empty';

    public function __construct(
        private CheckoutContexts $contexts,
        private CheckoutSteps $steps,
        private CheckoutSettings $settings,
        private Calculator $calculator,
        private Orders $orders,
        private Money $money,
    ) {}

    /** @return array<string, mixed> */
    public function of(Cart $cart): array
    {
        $context = $this->contexts->of($cart);
        $state = $context->state;
        $answer = [
            'cart_id' => $cart->id,
            'locale' => $context->locale,
            'totals' => $state->toArray(),
            'steps' => [],
            'outcome' => self::ORDER,
            'code' => null,
            'message' => null,
            'errors' => [],
            'payable' => null,
            'payable_formatted' => null,
            'decrease' => null,
            'terms_checkbox' => $this->settings->terms() === CheckoutSettings::TERMS_CHECKBOX,
        ];

        if ($context->isEmpty()) {
            return [...$answer, 'outcome' => self::EMPTY];
        }

        // 1. The steps, as `ready()` of the confirmation checks them.
        foreach ($this->steps->for($context) as $step) {
            $errors = $step->check($context, Purpose::Order);
            $answer['steps'][] = [
                'key' => $step->key(),
                'title' => $step->title(),
                'summary' => $step->summary($context),
                'errors' => array_map(static fn ($error): array => $error->toArray(), $errors),
            ];

            foreach ($errors as $error) {
                $answer['errors'][$error->field ?? $step->key()][] = $error->message;
            }
        }

        if ($answer['errors'] !== []) {
            return [...$answer, 'outcome' => self::INVALID];
        }

        // 2. The count of the order, as `Orders::place()` makes it under the lock.
        $order = $this->calculator->calculate($cart, $context->locale, Purpose::Order);

        try {
            $this->orders->orderable($cart, $order);
        } catch (CartNotOrderable $e) {
            return [...$answer, 'outcome' => self::CONFLICT, 'code' => CheckoutAnswers::NOT_ORDERABLE, 'message' => self::notOrderable($e)];
        }

        // 3. The amount on the page against the order's.
        $shown = $context->draft->shown_payable ?? $state->payable;

        if ($order->payable > $shown) {
            return [...$answer, 'outcome' => self::CONFLICT, 'code' => CheckoutAnswers::PAYABLE_CHANGED, 'message' => (string) __('webx-orders::checkout.errors.payable-changed', [
                'was' => $this->format($shown, $state->currency),
                'now' => $this->format($order->payable, $order->currency),
            ], $context->locale)];
        }

        return [
            ...$answer,
            'payable' => $order->payable,
            'payable_formatted' => $this->format($order->payable, $order->currency),
            'shown_payable' => $shown,
            'decrease' => $order->payable < $shown ? $shown - $order->payable : null,
        ];
    }

    /** What the confirmation says of a cart that cannot be ordered: its lines' words, or the general one. */
    public static function notOrderable(CartNotOrderable $e): string
    {
        return implode(' ', array_map(static fn ($warning): string => $warning->message, $e->warnings))
            ?: (string) __('webx-orders::checkout.errors.not-orderable');
    }

    private function format(int $minor, ?string $currency): string
    {
        return $currency !== null ? $this->money->format($minor, $currency) : (string) $minor;
    }
}
