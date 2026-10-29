{{--
    The column of the totals (spec §23) and the confirmation: printed by the page and alone by
    `GET /checkout/summary`, so the script puts a fresh one in place. Every amount is the cart's
    formatted string; a row with nothing to say is not printed. `expected_payable` is the amount
    on the screen — what the order is placed with, and checked against (§24.4); `place_key` makes
    a repeat the same order (§24.3); the mark of time and the hidden field are the antispam (§28).
    «Delivery cost» is the delivery slot's (§22.2): an amount, «Free» with the old price crossed
    out, «at carrier rates» — then not in the amount to pay — or «choose a method».

    Classes: webx-orders-checkout__summary, __summary-title, __row, __row--discount,
    __row--delivery, __was, __lines, __line, __change, __benefit, __hint, __payable, __carrier,
    __place, __terms, __honeypot.
--}}
<div class="webx-orders-checkout__summary" id="checkout-summary">
    <h2 class="webx-orders-checkout__summary-title">{{ __('webx-orders::checkout.summary.title') }}</h2>
    <dl class="webx-orders-checkout__rows">
        <div class="webx-orders-checkout__row">
            <dt>{{ trans_choice('webx-orders::checkout.summary.units', $units, ['count' => $units]) }}</dt>
            <dd>{{ $subtotal }}</dd>
        </div>
        @if ($discount !== null)
            <div class="webx-orders-checkout__row webx-orders-checkout__row--discount">
                <dt>{{ __('webx-orders::checkout.summary.discount') }}</dt>
                <dd>{{ $discount }}</dd>
            </div>
        @endif
        @if ($delivery !== null)
            <div class="webx-orders-checkout__row webx-orders-checkout__row--delivery" data-kind="{{ $delivery['kind'] }}">
                <dt>{{ __('webx-orders::checkout.summary.delivery') }}</dt>
                <dd>
                    @switch ($delivery['kind'])
                        @case ('fixed')
                            {{ $delivery['amount'] }}
                            @break
                        @case ('free')
                            {{ __('webx-orders::checkout.summary.delivery-free') }}@if ($delivery['was'] !== null) <s class="webx-orders-checkout__was">{{ $delivery['was'] }}</s>@endif
                            @break
                        @case ('carrier_rates')
                            {{ __('webx-orders::checkout.summary.delivery-carrier') }}@if ($delivery['estimate'] !== null) ≈ {{ $delivery['estimate'] }}@endif
                            @break
                        @default
                            {{ __('webx-orders::checkout.summary.delivery-choose') }}
                    @endswitch
                </dd>
            </div>
        @endif
        @foreach ($tenders as $tender)
            <div class="webx-orders-checkout__row">
                <dt>{{ $tender['label'] }}</dt>
                <dd>{{ $tender['amount_formatted'] }}</dd>
            </div>
        @endforeach
        <div class="webx-orders-checkout__row webx-orders-checkout__payable">
            <dt>{{ __('webx-orders::checkout.summary.payable') }}</dt>
            <dd>{{ $payable_formatted }}</dd>
        </div>
    </dl>
    @if (($delivery['kind'] ?? null) === 'carrier_rates')
        <p class="webx-orders-checkout__carrier">{{ __('webx-orders::checkout.summary.delivery-extra') }}</p>
    @endif
    @if ($discounts !== [])
        <ul class="webx-orders-checkout__discounts">
            @foreach ($discounts as $label)
                <li>{{ $label }}</li>
            @endforeach
        </ul>
    @endif
    @foreach ($benefits as $benefit)
        <p class="webx-orders-checkout__benefit">{{ $benefit }}</p>
    @endforeach
    @foreach ($hints as $hint)
        <p class="webx-orders-checkout__hint">{{ $hint }}</p>
    @endforeach
    <details class="webx-orders-checkout__lines">
        <summary>{{ __('webx-orders::checkout.summary.lines') }}</summary>
        <ul>
            @foreach ($lines as $line)
                <li class="webx-orders-checkout__line">
                    <span>{{ $line['name'] }} × {{ $line['qty'] }}</span>
                    <span>{{ $line['line_total_formatted'] }}</span>
                    @if ($line['price_change'] !== null)
                        <span class="webx-orders-checkout__change">{{ __('webx-orders::checkout.summary.price-'.$line['price_change']) }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </details>
    <x-webx-form :action="$action" :id="$form" class="webx-orders-checkout__place">
        <input type="hidden" name="expected_payable" value="{{ $payable }}">
        <input type="hidden" name="place_key" value="{{ $place_key }}">
        <input type="hidden" name="{{ \WebxUi\Orders\Checkout\CheckoutAntispam::TIMESTAMP }}" value="{{ $timestamp }}">
        @include('webx-orders::checkout.parts.honeypot', ['id' => $form.'-hp'])
        @if ($terms === 'checkbox')
            <x-webx-field type="consent" name="terms" :checked="$accepted" required>
                {{ __('webx-orders::checkout.terms.agree') }}@foreach ($documents as $document){{ $loop->first ? ': ' : ', ' }}<a href="{{ $document['url'] }}" target="_blank" rel="noopener">{{ $document['title'] }}</a>@endforeach
            </x-webx-field>
        @endif
        <x-webx-form.submit>{{ __('webx-orders::checkout.summary.place') }}</x-webx-form.submit>
        @if ($terms === 'notice')
            <p class="webx-orders-checkout__terms">
                {{ __('webx-orders::checkout.terms.notice') }}@foreach ($documents as $document){{ $loop->first ? ': ' : ', ' }}<a href="{{ $document['url'] }}" target="_blank" rel="noopener">{{ $document['title'] }}</a>@endforeach
            </p>
        @endif
    </x-webx-form>
</div>
