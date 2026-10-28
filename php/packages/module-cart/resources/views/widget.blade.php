{{--
    The inside of the cart's panel (§9.2), one view for the three modes: `$mode` is drawer, dialog
    or dropdown, `$controls` whether the steppers and the remove buttons are drawn, `$cart` the
    state as the API answers it (CartState::toArray()), `$checkout` where the button leads or
    null, `$warnings` the warnings by line id (the cart's own under ''), each
    `{message, notice}` — a notice (a price that moved) is not a problem.

    Every sum is the server's `*_formatted` string: the widget never formats money (decision 32).
    Points: cart.totals.before, cart.totals.after, cart.footer; cart.line.after in widget.line.
--}}
@if ($cart['lines'] === [])
    @include('webx-cart::widget.empty')
@else
    @include('webx-cart::widget.lines', ['lines' => $cart['lines'], 'children' => false])

    @foreach ($warnings[''] ?? [] as $warning)
        <p class="webx-cart__warning">{{ $warning['message'] }}</p>
    @endforeach

    @include('webx-cart::widget.totals')
@endif

{!! \WebxUi\Cart\View\Points::render('cart.footer', ['cart' => $cart, 'mode' => $mode]) !!}

@if ($checkout !== null && $cart['lines'] !== [])
    <a class="webx-cart__checkout" href="{{ $checkout }}">{{ __('webx-cart::widget.checkout') }}</a>
@endif
