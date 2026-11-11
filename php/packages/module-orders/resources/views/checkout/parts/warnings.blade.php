{{--
    The lines that cannot be bought (spec §20.4), in the cart's own words, above the steps. The
    confirmation stays active: pressing it answers with the same list. «Open the cart» opens the
    cart's dialog the page prints (`data-webx-cart-edit`); without JavaScript it is the link to
    `#webx-cart` of the widget.

    Classes: webx-orders-checkout__warnings, __warning.
--}}
@if ($warnings !== [])
    <div class="webx-orders-checkout__warnings" id="checkout-warnings" role="alert">
        <ul>
            @foreach ($warnings as $warning)
                <li class="webx-orders-checkout__warning">{{ $warning }}</li>
            @endforeach
        </ul>
        <a href="#{{ \WebxUi\Cart\View\Components\Widget::ID }}" data-webx-cart-edit aria-haspopup="dialog">{{ __('webx-orders::checkout.page.open-cart') }}</a>
    </div>
@endif
