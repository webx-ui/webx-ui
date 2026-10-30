{{--
    «Thank you» after the checkout (spec §26), only for the session that placed the order: its
    number, where the confirmation went, how much less it came to than the buyer saw (§24.4), the
    order as its page prints it, and the link to that page. Nobody but the buyer reads it:
    `noindex`, `no-store`.

    $order — `WebxUi\Orders\Site\OrderPage::of()`; $email — null when no letter goes to the buyer;
    $pay — «Pay» of the payment slot for an order that awaits payment (§22.3), null — none; the
    order's body says it awaits payment either way.
    The stack `checkout.thanks` is the place of a counter's code (`module-snippets`); the order is in
    the `data-*` of its block; `checkout.js` dispatches `webx:checkout:placed` with them (§30).
    $register — the order's e-mail for «Save your orders in an account» of a guest, where visitors
    register (§26): the dialog of the registration with the address filled in; null — none.

    Override: <layer>/views/vendor/webx-orders/checkout/thanks.blade.php.
    Classes: webx-orders-thanks, __title, __sent, __decrease, __pay, __link, __register, __analytics.
--}}
@php($title = __('webx-orders::checkout.thanks.title', ['number' => $order['number']]))
<x-dynamic-component :component="\WebxUi\Orders\Checkout\CheckoutLayout::name()" handle="checkout.thanks">
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
        @if ($register !== null)
            <div class="webx-orders-thanks__register">
                <p>{{ __('webx-orders::checkout.thanks.register') }}</p>
                {{-- Dynamic: a tag of a module the site may not have fails the compiling of this view. --}}
                <x-dynamic-component component="webx-users::sign-in-button" view="register" :email="$register">{{ __('webx-orders::checkout.thanks.register-button') }}</x-dynamic-component>
            </div>
        @endif
        <div class="webx-orders-thanks__analytics" hidden data-number="{{ $analytics['number'] }}" data-payable="{{ $analytics['payable'] }}" data-currency="{{ $analytics['currency'] }}">
            @stack('checkout.thanks')
        </div>
    </div>

    @push('scripts')
        <script type="module" src="{{ \WebxUi\Orders\Checkout\Http\ScriptController::url() }}"></script>
    @endpush
</x-dynamic-component>
