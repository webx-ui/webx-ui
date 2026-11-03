{{--
    The bank details of an order (spec §12.2): the recipient, the account, the purpose with the
    order's number, the note on the term — in the order's language, from the order's `payment`
    block (a copy: a later edit of the method does not change it). Nothing for other methods.

    <x-webx-payments::instructions :order="$order" /> — an `Order` or an `OrderData`; or :lines —
    what `WebxUi\Payments\Instructions::of()` gave.

    Override: <layer>/views/vendor/webx-payments/components/instructions.blade.php.
    Classes: webx-payments-instructions, __title, __list, __row, __label, __value (+ __row--<field>).
--}}
@props(['order' => null, 'lines' => null])
@php
    if ($lines === null && $order !== null) {
        $data = $order instanceof \WebxUi\Orders\Data\OrderData ? $order : null;
        $block = $data?->payment ?? (is_array($order->payment ?? null) ? $order->payment : null);
        $number = (string) ($data?->number ?? $order->number ?? '');
        $lines = \WebxUi\Payments\Instructions::of($block, $number, app()->getLocale());
    }
@endphp
@if (is_array($lines) && $lines !== [])
    <div {{ $attributes->class('webx-payments-instructions') }}>
        <p class="webx-payments-instructions__title">{{ __('webx-payments::buyer.instructions.title') }}</p>
        <dl class="webx-payments-instructions__list">
            @foreach ($lines as $field => $text)
                <div class="webx-payments-instructions__row webx-payments-instructions__row--{{ $field }}">
                    <dt class="webx-payments-instructions__label">{{ __('webx-payments::buyer.instructions.'.$field) }}</dt>
                    <dd class="webx-payments-instructions__value">{{ $text }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
@endif
