{{--
    The page of payment of an order (spec §8.4) behind its signed link, in the site's layout with
    the handle `payments.pay` (the orders' layout, `webx-orders.layout`). This stage: paid,
    cancelled, «we are checking», «did not go through» and «Pay» with an online method, the bank
    details of a transfer. Nobody but the holder of the link reads it: `noindex`, `no-store`.

    Override: <layer>/views/vendor/webx-payments/pay/page.blade.php.
    Classes: webx-payments-pay, __title, __amount, __message (+ --error), __form, __button, __link.
--}}
@php($title = __('webx-payments::buyer.pay.title', ['number' => $page['number']]))
<x-dynamic-component :component="config('webx-orders.layout') ?: 'webx-orders::standalone'" handle="payments.pay">
    <x-slot:head>
        <title>{{ $title }}@if (is_string(config('app.name')) && config('app.name') !== '') — {{ config('app.name') }}@endif</title>
        <meta name="robots" content="noindex">
    </x-slot:head>
    <div class="webx-payments-pay">
        <h1 class="webx-payments-pay__title">{{ $title }}</h1>

        @if ($page['error'])
            <p class="webx-payments-pay__message webx-payments-pay__message--error" role="alert">{{ $page['error'] }}</p>
        @endif

        @switch($page['state'])
            @case('cancelled')
                <p class="webx-payments-pay__message">{{ __('webx-payments::buyer.pay.cancelled', ['number' => $page['number']]) }}</p>
                @break
            @case('paid')
                <p class="webx-payments-pay__message">{{ __('webx-payments::buyer.pay.paid', ['number' => $page['number']]) }}</p>
                @break
            @case('checking')
                <p class="webx-payments-pay__message">{{ __('webx-payments::buyer.pay.checking') }}</p>
                @break
            @case('on_receipt')
                <p class="webx-payments-pay__message">{{ __('webx-payments::buyer.pay.on_receipt') }}</p>
                @break
            @case('transfer')
                <p class="webx-payments-pay__amount">{{ __('webx-payments::buyer.pay.due', ['amount' => $page['due']]) }}</p>
                <x-webx-payments::instructions :lines="$page['instructions']" />
                @break
            @default
                @if ($page['failed'])
                    <p class="webx-payments-pay__message webx-payments-pay__message--error">{{ __('webx-payments::buyer.pay.failed') }}</p>
                @endif
                <p class="webx-payments-pay__amount">{{ __('webx-payments::buyer.pay.due', ['amount' => $page['due']]) }}</p>
                @if ($page['attempts'])
                    <form class="webx-payments-pay__form" method="post" action="{{ $page['attempts'] }}">
                        @csrf
                        <button class="webx-payments-pay__button" type="submit">{{ __('webx-payments::buyer.status.pay') }} — {{ $page['method'] }}</button>
                    </form>
                @endif
        @endswitch

        @if ($page['order'])
            <p><a class="webx-payments-pay__link" href="{{ $page['order'] }}">{{ __('webx-payments::buyer.pay.order_link') }}</a></p>
        @endif
    </div>
</x-dynamic-component>
