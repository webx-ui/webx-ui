{{--
    The column of the totals (spec §23) and the confirmation: printed by the page and alone by
    `GET /checkout/summary`, so the script puts a fresh one in place. Every amount is the cart's
    formatted string; a row with nothing to say is not printed. `expected_payable` is the amount
    on the screen — what the order is placed with, and checked against (§24.4); `place_key` makes
    a repeat the same order (§24.3); the mark of time and the hidden field are the antispam (§28).
    «Delivery cost» is the delivery slot's (§22.2): an amount, «Free» with the old price crossed
    out, «at carrier rates» — then not in the amount to pay — or «choose a method».

    «Order contents» only lists the lines (§23.5): the name, the quantity, the sum, what changed
    and what can no longer be bought, in the cart's own words. A line that cannot be bought has
    no sum, as in the dialog of the cart: it is in neither «N items for» nor the amount beside it,
    and when no line is left to buy that row says so instead of «0 items for 0.00». «Edit» opens the cart's dialog
    (`data-webx-cart-edit`, the widget the page prints), where the lines change; without
    JavaScript it is the link to `#webx-cart` of the widget.

    Nothing left to buy (`placeable` false, §20.4): the button is `disabled`, a line above it says to
    change the cart first; «Open the cart» stays in the warning over the steps.

    Classes: webx-orders-checkout__summary, __summary-title, __row, __row--discount,
    __row--delivery, __was, __lines, __lines-head, __edit, __line, __line-name, __line-link,
    __line-qty, __line-total, __change, __gone, __benefit, __hint, __payable, __carrier, __place, __blocked,
    __terms, __honeypot.
--}}
<div class="webx-orders-checkout__summary" id="checkout-summary">
    <h2 class="webx-orders-checkout__summary-title" tabindex="-1">{{ __('webx-orders::checkout.summary.title') }}</h2>
    <dl class="webx-orders-checkout__rows">
        <div class="webx-orders-checkout__row">
            @if ($units > 0)
                <dt>{{ trans_choice('webx-orders::checkout.summary.units', $units, ['count' => $units]) }}</dt>
                <dd>{{ $subtotal }}</dd>
            @else
                <dt>{{ __('webx-orders::checkout.summary.nothing') }}</dt>
            @endif
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
        <summary><span class="webx-orders-checkout__lines-head">{{ __('webx-orders::checkout.summary.lines') }}</span> <a class="webx-orders-checkout__edit" href="#{{ \WebxUi\Cart\View\Components\Widget::ID }}" data-webx-cart-edit aria-haspopup="dialog">{{ __('webx-orders::checkout.summary.edit') }}</a></summary>
        <ul>
            @foreach ($lines as $line)
                <li @class(['webx-orders-checkout__line', 'is-unavailable' => ! ($line['available'] ?? true)]) data-line="{{ $line['id'] }}">
                    <span class="webx-orders-checkout__line-name">@if (($line['url'] ?? null) !== null)<a class="webx-orders-checkout__line-link" href="{{ $line['url'] }}">{{ $line['name'] }}</a>@else{{ $line['name'] }}@endif <span class="webx-orders-checkout__line-qty">× {{ $line['qty'] }}</span></span>
                    @if ($line['available'] ?? true)
                        <span class="webx-orders-checkout__line-total">{{ $line['line_total_formatted'] }}</span>
                    @endif
                    @if ($line['gone'] ?? false)
                        <span class="webx-orders-checkout__gone">{{ __('webx-cart::widget.gone') }}</span>
                    @elseif (! ($line['available'] ?? true))
                        <span class="webx-orders-checkout__gone">{{ __('webx-cart::widget.unavailable') }}</span>
                    @elseif ($line['price_change'] !== null)
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
        @if (($captcha ?? null) !== null)
            <x-webx-captcha :action="$captcha" :id="$form.'-captcha'" :refusal="isset($errors) ? $errors->get('captcha') : []" class="webx-orders-checkout__captcha" />
        @endif
        @if ($terms === 'checkbox')
            <x-webx-field type="consent" name="terms" :checked="$accepted" required>
                {{ __('webx-orders::checkout.terms.agree') }}@foreach ($documents as $document){{ $loop->first ? ': ' : ', ' }}<a href="{{ $document['url'] }}" target="_blank" rel="noopener">{{ $document['title'] }}</a>@endforeach
            </x-webx-field>
        @endif
        @unless ($placeable)
            <p class="webx-orders-checkout__blocked" id="{{ $form }}-blocked">{{ __('webx-orders::checkout.summary.blocked') }}</p>
        @endunless
        <x-webx-form.submit :disabled="! $placeable" :aria-disabled="$placeable ? null : 'true'" :aria-describedby="$placeable ? null : $form.'-blocked'">{{ __('webx-orders::checkout.summary.place') }}</x-webx-form.submit>
        @if ($terms === 'notice')
            <p class="webx-orders-checkout__terms">
                {{ __('webx-orders::checkout.terms.notice') }}@foreach ($documents as $document){{ $loop->first ? ': ' : ', ' }}<a href="{{ $document['url'] }}" target="_blank" rel="noopener">{{ $document['title'] }}</a>@endforeach
            </p>
        @endif
    </x-webx-form>
</div>
