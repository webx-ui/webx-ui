{{--
    The chain of totals (§5.4): the sum of the lines, the adjustments, the total, the tenders, what
    is left to pay; then the hints. With no adjustments and no tenders the sum of the lines is what
    is paid, and one row says it.
--}}
{!! \WebxUi\Cart\View\Points::render('cart.totals.before', ['cart' => $cart, 'mode' => $mode]) !!}

<dl class="webx-cart__totals">
    @if ($cart['adjustments'] !== [] || $cart['tenders'] !== [])
        <div class="webx-cart__total-row">
            <dt>{{ __('webx-cart::widget.subtotal') }}</dt>
            <dd>{{ $cart['subtotal_formatted'] }}</dd>
        </div>
        @foreach ($cart['adjustments'] as $adjustment)
            <div class="webx-cart__total-row">
                <dt>{{ $adjustment['label'] ?? __('webx-cart::widget.discount') }}</dt>
                <dd>{{ $adjustment['amount_formatted'] }}</dd>
            </div>
        @endforeach
        @if ($cart['tenders'] !== [])
            <div class="webx-cart__total-row">
                <dt>{{ __('webx-cart::widget.total') }}</dt>
                <dd>{{ $cart['total_formatted'] }}</dd>
            </div>
            @foreach ($cart['tenders'] as $tender)
                <div class="webx-cart__total-row">
                    <dt>{{ $tender['label'] }}</dt>
                    <dd>{{ $tender['amount_formatted'] }}</dd>
                </div>
            @endforeach
        @endif
    @endif
    <div class="webx-cart__total-row webx-cart__payable">
        <dt>{{ $cart['tenders'] !== [] ? __('webx-cart::widget.payable') : __('webx-cart::widget.total') }}</dt>
        <dd>{{ $cart['payable_formatted'] }}</dd>
    </div>
</dl>

@if ($cart['hints'] !== [])
    <ul class="webx-cart__hints">
        @foreach ($cart['hints'] as $hint)
            <li>{{ $hint['text'] }}</li>
        @endforeach
    </ul>
@endif

{!! \WebxUi\Cart\View\Points::render('cart.totals.after', ['cart' => $cart, 'mode' => $mode]) !!}
