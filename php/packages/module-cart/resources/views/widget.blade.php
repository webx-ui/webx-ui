{{--
    The inside of the cart's panel (§9.2), one view for the main view and the mini cart: `$mode` is
    drawer or dialog, or dropdown for the mini cart, `$controls` whether the steppers and the
    remove buttons are drawn, `$edit` whether the button to the main view is (the mini cart),
    `$cart` the state as the API answers it (CartState::toArray()), `$checkout` where the button
    leads or null, `$warnings` the warnings by line id (the cart's own under ''), each
    `{message, notice}` — a notice (a price that moved, a line already at its maximum) is not a
    problem.

    Every sum is the server's `*_formatted` string: the widget never formats money (decision 32).
    Points: cart.totals.before, cart.totals.after, cart.footer; cart.line.after in widget.line.
--}}
@if ($cart['lines'] === [])
    @include('webx-cart::widget.empty')
@else
    @include('webx-cart::widget.lines', ['lines' => $cart['lines'], 'children' => false])

    @foreach ($warnings[''] ?? [] as $warning)
        <p @class(['webx-cart__notice' => $warning['notice'], 'webx-cart__warning' => ! $warning['notice']])>{{ $warning['message'] }}</p>
    @endforeach

    @include('webx-cart::widget.totals')
@endif

{!! \WebxUi\Cart\View\Points::render('cart.footer', ['cart' => $cart, 'mode' => $mode]) !!}

@if (($edit ?? false) && $cart['lines'] !== [])
    <a class="webx-cart__edit" href="#{{ \WebxUi\Cart\View\Components\Widget::ID }}" data-webx-cart-edit>{{ __('webx-cart::widget.edit') }}</a>
@endif

@if ($checkout !== null && $cart['lines'] !== [])
    <a class="webx-cart__checkout" href="{{ $checkout }}">{{ __('webx-cart::widget.checkout') }}</a>
@endif
