{{--
    The page of payment of an order (spec §8.4) behind its signed link, in the site's layout with
    the handle `payments.pay` (the orders' layout, `webx-orders.layout`): paid, cancelled, held,
    «we are checking» (refreshes itself for two minutes, then «Check again»), «did not go through»
    with the reason and «Pay» with an online method, «Payment not completed» with «Pay again» when
    the buyer came back without beginning the payment, «The bank declined the card» with the reason,
    «Pay again» and «Check again» when the gateway keeps the payment open for another card, «Top up»
    of a paid order that owes more, the bank details of a transfer. «Choose another method» opens
    every other method of the order wherever it is not paid and nothing is being checked
    (`pay.others`); of an order on receipt it stands open as «Pay online now» if any is online.
    Nobody but the holder of the link reads it: `noindex`, `no-store`.

    Override: <layer>/views/vendor/webx-payments/pay/page.blade.php.
    Classes: webx-payments-pay, __title, __amount, __message (+ --error), __form, __button, __link.
--}}
@php($title = __('webx-payments::buyer.pay.title', ['number' => $page['number']]))
<x-dynamic-component :component="config('webx-orders.layout') ?: 'webx-orders::standalone'" handle="payments.pay">
    <x-slot:head>
        <title>{{ $title }}@if (is_string(config('app.name')) && config('app.name') !== '') — {{ config('app.name') }}@endif</title>
        <meta name="robots" content="noindex">
        @if ($page['refresh'])
            <meta http-equiv="refresh" content="{{ $page['refresh'] }}">
        @endif
    </x-slot:head>
    <div class="webx-payments-pay">
        <h1 class="webx-payments-pay__title">{{ $title }}</h1>

        @if ($page['error'])
            <p class="webx-payments-pay__message webx-payments-pay__message--error" role="alert">{{ $page['error'] }}</p>
        @endif

        @if ($page['status'])
            <p class="webx-payments-pay__message" role="status">{{ $page['status'] }}</p>
        @endif

        @switch($page['state'])
            @case('cancelled')
                <p class="webx-payments-pay__message">{{ __('webx-payments::buyer.pay.cancelled', ['number' => $page['number']]) }}</p>
                @break
            @case('paid')
                <p class="webx-payments-pay__message">{{ __('webx-payments::buyer.pay.paid', ['number' => $page['number']]) }}</p>
                @break
            @case('held')
                <p class="webx-payments-pay__message">{{ __('webx-payments::buyer.pay.held') }}</p>
                @break
            @case('checking')
                <p class="webx-payments-pay__message" role="status">{{ __('webx-payments::buyer.pay.checking') }}</p>
                @break
            @case('checking_long')
                <p class="webx-payments-pay__message" role="status">{{ __('webx-payments::buyer.pay.checking_long') }}</p>
                <p><a class="webx-payments-pay__link" href="{{ $page['check'] }}">{{ __('webx-payments::buyer.pay.check_again') }}</a></p>
                @break
            @case('top_up')
                <p class="webx-payments-pay__amount">{{ __('webx-payments::buyer.pay.top_up', ['amount' => $page['due']]) }}</p>
                @if ($page['wait'])
                    <p class="webx-payments-pay__message" role="note">{{ __('webx-payments::buyer.pay.wait') }}</p>
                @endif
                @foreach ($page['online'] as $option)
                    <form class="webx-payments-pay__form" method="post" action="{{ $page['attempts'] }}">
                        @csrf
                        <input type="hidden" name="method" value="{{ $option['key'] }}">
                        <button class="webx-payments-pay__button" type="submit">{{ __('webx-payments::buyer.pay.top_up_button') }} — {{ $option['title'] }}</button>
                    </form>
                @endforeach
                @break
            @case('on_receipt')
                <p class="webx-payments-pay__message">{{ __('webx-payments::buyer.pay.on_receipt') }}</p>
                @if ($page['switch'])
                    <p class="webx-payments-pay__amount">{{ __('webx-payments::buyer.pay.due', ['amount' => $page['due']]) }}</p>
                @endif
                @break
            @case('unfinished')
                <p class="webx-payments-pay__message" role="status">{{ __('webx-payments::buyer.pay.unfinished') }}</p>
                <p class="webx-payments-pay__amount">{{ __('webx-payments::buyer.pay.due', ['amount' => $page['due']]) }}</p>
                <p class="webx-payments-pay__message" role="note">{{ __('webx-payments::buyer.pay.wait') }}</p>
                @if ($page['attempts'])
                    <form class="webx-payments-pay__form" method="post" action="{{ $page['attempts'] }}">
                        @csrf
                        <button class="webx-payments-pay__button" type="submit">{{ __('webx-payments::buyer.pay.pay_again') }} — {{ $page['method'] }}</button>
                    </form>
                @endif
                @break
            @case('declined')
                <p class="webx-payments-pay__message webx-payments-pay__message--error" role="alert">{{ __('webx-payments::buyer.pay.declined') }}</p>
                @if ($page['reason'])
                    <p class="webx-payments-pay__message">{{ $page['reason'] }}</p>
                @endif
                <p class="webx-payments-pay__amount">{{ __('webx-payments::buyer.pay.due', ['amount' => $page['due']]) }}</p>
                <p class="webx-payments-pay__message" role="note">{{ __('webx-payments::buyer.pay.wait') }}</p>
                @if ($page['attempts'])
                    <form class="webx-payments-pay__form" method="post" action="{{ $page['attempts'] }}">
                        @csrf
                        <button class="webx-payments-pay__button" type="submit">{{ __('webx-payments::buyer.pay.pay_again') }} — {{ $page['method'] }}</button>
                    </form>
                @endif
                @break
            @case('transfer')
                <p class="webx-payments-pay__amount">{{ __('webx-payments::buyer.pay.due', ['amount' => $page['due']]) }}</p>
                <x-webx-payments::instructions :lines="$page['instructions']" />
                @break
            @default
                @if ($page['failed'])
                    <p class="webx-payments-pay__message webx-payments-pay__message--error">{{ __('webx-payments::buyer.pay.failed') }}@if ($page['reason']): {{ $page['reason'] }}@endif</p>
                @endif
                <p class="webx-payments-pay__amount">{{ __('webx-payments::buyer.pay.due', ['amount' => $page['due']]) }}</p>
                @if ($page['wait'])
                    <p class="webx-payments-pay__message" role="note">{{ __('webx-payments::buyer.pay.wait') }}</p>
                @endif
                @if ($page['attempts'])
                    <form class="webx-payments-pay__form" method="post" action="{{ $page['attempts'] }}">
                        @csrf
                        <button class="webx-payments-pay__button" type="submit">{{ __('webx-payments::buyer.status.pay') }} — {{ $page['method'] }}</button>
                    </form>
                @endif
        @endswitch

        @include('webx-payments::pay.others', [
            'others' => $page['others'],
            'switch' => $page['switch'],
            'toggle' => $page['state'] === 'on_receipt' && $page['online'] !== [] ? __('webx-payments::buyer.pay.online_now') : null,
        ])

        @if ($page['state'] === 'declined' && $page['check'])
            <p><a class="webx-payments-pay__link" href="{{ $page['check'] }}">{{ __('webx-payments::buyer.pay.check_again') }}</a></p>
        @endif

        @if ($page['order'])
            <p><a class="webx-payments-pay__link" href="{{ $page['order'] }}">{{ __('webx-payments::buyer.pay.order_link') }}</a></p>
        @endif
    </div>
</x-dynamic-component>
