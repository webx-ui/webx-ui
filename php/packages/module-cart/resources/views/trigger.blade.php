{{-- The inside of the trigger: the icon, the words a screen reader says, the counter the server printed and the script keeps. --}}
@include('webx-cart::icons.cart')
<span class="webx-cart__label">{{ __('webx-cart::widget.open') }}</span>
<span @class(['webx-cart__count', 'is-empty' => $count === 0]) data-webx-cart-count>{{ $count }}</span>
