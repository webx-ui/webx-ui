{{--
    «Thank you» after the checkout (spec §26), only for the session that placed the order: its
    number, where the confirmation went, how much less it came to than the buyer saw (§24.4), the
    order as its page prints it, and the link to that page. Nobody but the buyer reads it:
    `noindex`, `no-store`.

    $order — `WebxUi\Orders\Site\OrderPage::of()`; $email — null when no letter goes to the buyer;
    $pay — «Pay» of the payment slot for an order that awaits payment (§22.3), null — none; the
    order's body says it awaits payment either way.
    The stack `checkout.thanks` is the place of a counter's code (`module-snippets`); the order is in
    the `data-*` of its block.

    Override: <layer>/views/vendor/webx-orders/checkout/thanks.blade.php.
    Classes: webx-orders-thanks, __title, __sent, __decrease, __pay, __link, __analytics.
--}}
@php($title = __('webx-orders::checkout.thanks.title', ['number' => $order['number']]))
<x-dynamic-component :component="config('webx-orders.checkout.layout') ?: (config('webx-orders.layout') ?: 'webx-orders::standalone')" handle="checkout.thanks">
    <x-slot:head>
        <title>{{ $title }}@if (is_string(config('app.name')) && config('app.name') !== '') — {{ config('app.name') }}@endif</title>
        <meta name="robots" content="noindex">
    </x-slot:head>
    <div class="webx-orders-thanks">
        <h1 class="webx-orders-thanks__title">{{ $title }}</h1>
        @if ($email !== null)
            <p class="webx-orders-thanks__sent">{{ __('webx-orders::checkout.thanks.sent', ['email' => $email]) }}</p>
        @endif
        @if ($decrease !== null)
            <p class="webx-orders-thanks__decrease" role="status">{{ __('webx-orders::checkout.thanks.decrease', ['amount' => $decrease]) }}</p>
        @endif
        @if ($pay !== null)
            <p class="webx-orders-thanks__pay"><a href="{{ $pay }}">{{ __('webx-orders::checkout.thanks.pay') }}</a></p>
        @endif
        @include('webx-orders::order.body')
        @if ($link !== '')
            <p class="webx-orders-thanks__link"><a href="{{ $link }}">{{ __('webx-orders::checkout.thanks.order-page') }}</a></p>
        @endif
        <div class="webx-orders-thanks__analytics" hidden data-number="{{ $analytics['number'] }}" data-payable="{{ $analytics['payable'] }}" data-currency="{{ $analytics['currency'] }}">
            @stack('checkout.thanks')
        </div>
    </div>
</x-dynamic-component>
