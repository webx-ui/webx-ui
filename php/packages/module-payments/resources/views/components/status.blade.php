{{--
    One line of an order's payment (spec §10.3): «Paid», or «Awaiting payment» with «Pay» when
    there is a page to pay on. Nothing for a cancelled order or one that does not await payment.

    <x-webx-payments::status :order="$order" :pay="$url" /> — an `Order`; $pay — null: no link.

    Override: <layer>/views/vendor/webx-payments/components/status.blade.php.
    Classes: webx-payments-status (+ webx-payments-status--paid, --awaiting), __pay.
--}}
@props(['order', 'pay' => null])
@if ($order->cancelled_at === null)
    @if ($order->paid_at !== null)
        <p {{ $attributes->class(['webx-payments-status', 'webx-payments-status--paid']) }}>{{ __('webx-payments::buyer.status.paid') }}</p>
    @elseif ($order->awaiting_payment)
        <p {{ $attributes->class(['webx-payments-status', 'webx-payments-status--awaiting']) }}>
            {{ __('webx-payments::buyer.status.awaiting') }}
            @if ($pay !== null)
                <a class="webx-payments-status__pay" href="{{ $pay }}">{{ __('webx-payments::buyer.status.pay') }}</a>
            @endif
        </p>
    @endif
@endif
