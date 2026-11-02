<?php

declare(strict_types=1);

namespace WebxUi\Delivery\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use WebxUi\Cart\CartHandle;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Delivery\Checkout\StepView;

/**
 * `<x-webx-delivery::step />` — the whole delivery step (§12.3): the checkout's slot prints it, and
 * a page of a site's own may as well. Without props it is the visitor's own cart, counted for the
 * checkout; the checkout hands its counted state and the cart's id instead.
 *
 * `name` — the prefix of the fields, when the step stands inside a form of its owner; `event` —
 * what the script dispatches on `document` after each change besides `webx:delivery:changed`.
 */
final class Step extends Component
{
    public function __construct(
        public ?CartState $state = null,
        public ?int $cart = null,
        public ?string $name = null,
        public ?string $event = null,
    ) {}

    public function render(): View
    {
        $state = $this->state;
        $cart = $this->cart;

        if ($state === null) {
            $handle = app(CartHandle::class);
            $model = $handle->cart();
            $cart = $model?->id;
            $state = $model === null
                ? app(Calculator::class)->empty($handle->locale())
                : app(Calculator::class)->calculate($model, $handle->locale(), Purpose::Checkout);
        }

        $errors = view()->shared('errors');

        return view('webx-delivery::step', [
            'step' => app(StepView::class)->build($state, $cart, $state->locale, is_object($errors) ? $errors : null, $this->name),
            'event' => $this->event,
        ]);
    }
}
