{{--
    The checkout of an empty cart (spec §20.4): no steps, the way back. The draft, if any, stays —
    the contacts are there when the cart fills again.

    Override: <layer>/views/vendor/webx-orders/checkout/empty.blade.php.
    Classes: webx-orders-checkout, is-empty, __empty.
--}}
@php($title = __('webx-orders::checkout.page.title'))
<x-dynamic-component :component="\WebxUi\Orders\Checkout\CheckoutLayout::name()" handle="checkout.page">
    <x-slot:head>
        <title>{{ $title }}@if (is_string(config('app.name')) && config('app.name') !== '') — {{ config('app.name') }}@endif</title>
        <meta name="robots" content="noindex">
    </x-slot:head>
    <div class="webx-orders-checkout is-empty">
        @include('webx-orders::checkout.parts.title')
        <p class="webx-orders-checkout__empty">{{ __('webx-orders::checkout.page.empty') }}</p>
        <p><a href="{{ $home }}">{{ __('webx-orders::checkout.page.shopping') }}</a></p>
    </div>
</x-dynamic-component>
