{{--
    The column of the totals (spec §23) and the confirmation: printed by the page and alone by
    `GET /checkout/summary`, so the script puts a fresh one in place. Every amount is the cart's
    formatted string; a row with nothing to say is not printed. `expected_payable` is the amount
    on the screen — what the order is placed with, and checked against (§24.4); `place_key` makes
    a repeat the same order (§24.3); the mark of time and the hidden field are the antispam (§28).
    «Delivery cost» is the delivery slot's (§22.2): an amount, «Free» with the old price crossed
    out, «at carrier rates» — then not in the amount to pay — or «choose a method».

    «Order contents» is where the cart changes (§23.5): the forms are the cart's own API, so without
    JavaScript each press comes back here; `checkout.js` sends them in place. Which line has which
    control is the widget's rule: a stored line steps, a generated one is declined when it may be.

    Classes: webx-orders-checkout__summary, __summary-title, __row, __row--discount,
    __row--delivery, __was, __lines, __line, __line-link, __line-error, __line-controls, __qty,
    __qty-button, __qty-value, __remove, __change, __benefit, __hint, __payable, __carrier,
    __place, __terms, __honeypot.
--}}
<div class="webx-orders-checkout__summary" id="checkout-summary">
    <h2 class="webx-orders-checkout__summary-title" tabindex="-1">{{ __('webx-orders::checkout.summary.title') }}</h2>
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
    <details class="webx-orders-checkout__lines" open>
        <summary>{{ __('webx-orders::checkout.summary.lines') }}</summary>
        @if (isset($errors) && $errors->has('qty'))
            <p class="webx-orders-checkout__line-error" role="alert">{{ $errors->first('qty') }}</p>
        @endif
        <ul>
            @foreach ($lines as $line)
                @php
                    $stored = ($line['source'] ?? null) === null;
                    $stepper = $stored && ! ($line['gone'] ?? false);
                    $removable = $stored || ($line['declinable'] ?? false);
                    $limit = (int) config('webx-cart.qty_max', 999);
                    $max = min((int) ($line['max_qty'] ?? $limit), $limit);
                @endphp
                <li class="webx-orders-checkout__line" data-line="{{ $line['id'] }}">
                    <span>@if (($line['url'] ?? null) !== null)<a class="webx-orders-checkout__line-link" href="{{ $line['url'] }}">{{ $line['name'] }}</a>@else{{ $line['name'] }}@endif @unless ($stepper)× {{ $line['qty'] }}@endunless</span>
                    <span>{{ $line['line_total_formatted'] }}</span>
                    @if ($line['price_change'] !== null)
                        <span class="webx-orders-checkout__change">{{ __('webx-orders::checkout.summary.price-'.$line['price_change']) }}</span>
                    @endif
                    @if ($stepper || $removable)
                        <div class="webx-orders-checkout__line-controls">
                            @if ($stepper)
                                <form class="webx-orders-checkout__qty" method="post" action="{{ route('webx-cart.items.update', ['id' => $line['id']]) }}" data-max="{{ $max }}" role="group" aria-label="{{ __('webx-cart::widget.quantity') }}: {{ $line['name'] }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="webx-orders-checkout__qty-button" name="qty" value="{{ $line['qty'] - 1 }}" data-control="decrease" aria-label="{{ __('webx-cart::widget.decrease') }}: {{ $line['name'] }}" @disabled($line['qty'] <= 1)>&minus;</button>
                                    <span class="webx-orders-checkout__qty-value" data-qty>{{ $line['qty'] }}</span>
                                    <button type="submit" class="webx-orders-checkout__qty-button" name="qty" value="{{ $line['qty'] + 1 }}" data-control="increase" aria-label="{{ __('webx-cart::widget.increase') }}: {{ $line['name'] }}" @disabled($line['qty'] >= $max)>+</button>
                                </form>
                            @endif
                            @if ($removable)
                                <form method="post" action="{{ route('webx-cart.items.destroy', ['id' => $line['id']]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="webx-orders-checkout__remove" data-control="remove" aria-label="{{ __($stored ? 'webx-cart::widget.remove' : 'webx-cart::widget.decline') }}: {{ $line['name'] }}">{{ __($stored ? 'webx-cart::widget.remove' : 'webx-cart::widget.decline') }}</button>
                                </form>
                            @endif
                        </div>
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
        @if (($captcha ?? null) !== null)
            <x-webx-captcha :action="$captcha" :id="$form.'-captcha'" :refusal="isset($errors) ? $errors->get('captcha') : []" class="webx-orders-checkout__captcha" />
        @endif
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
