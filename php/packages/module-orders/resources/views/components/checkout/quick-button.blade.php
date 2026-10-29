{{--
    `<x-webx-orders::checkout.quick-button type="catalog.product" :ref="$product->id" />` — «Buy in
    one click» (spec §29): a button beside «Buy» and the dialog it opens — a phone, a name if the
    buyer gives one, the agreement as the tab «Checkout» asks for it. The order is one line of this
    `type` and `ref` (`qty`, `options` as «Add to cart» takes them), without the buyer's cart; the
    answer — the shop's words and the order's number — takes the place of the fields.

    Printed only while the checkout and the setting `orders.checkout.quick.enabled` are on. Nothing
    personal and no CSRF token: the product's page may be cached for everybody. Without JavaScript
    the button is a link to `#<id>`, the dialog shows through `:target`, and the form is a plain
    POST that comes back to the page with its answer.

    The slot — the words, «Buy in one click» without one. Override:
    <layer>/views/vendor/webx-orders/components/checkout/quick-button.blade.php — keep the fields'
    names, the honeypot and the mark of time. Classes: webx-orders-quick, __button, __form, __terms.
--}}
@props(['type', 'ref', 'qty' => 1, 'options' => []])
@php
    $settings = app(\WebxUi\Orders\Checkout\CheckoutSettings::class);
    $shown = \WebxUi\Orders\Checkout\Checkout::enabled() && $settings->quick() && \Illuminate\Support\Facades\Route::has(\WebxUi\Orders\Checkout\CheckoutLinks::QUICK);
@endphp
@if ($shown)
    @php
        $locale = app()->getLocale();
        $options = is_array($options) ? $options : [];
        $id = 'webx-quick-'.strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $type.'-'.$ref), '-')).($options !== [] ? '-'.substr(md5((string) json_encode($options)), 0, 8) : '');
        $action = app(\WebxUi\Orders\Checkout\CheckoutLinks::class)->to(\WebxUi\Orders\Checkout\CheckoutLinks::QUICK, $locale);
        $terms = $settings->terms();
        $documents = $terms === 'none' ? [] : $settings->documents($locale);
    @endphp
    <a href="#{{ $id }}" {{ $attributes->class(['webx-orders-quick__button']) }} data-webx-dialog="{{ $id }}" rel="nofollow">{{ $slot->isEmpty() ? __('webx-orders::checkout.quick.button') : $slot }}</a>
    <x-webx-dialog :id="$id" :title="__('webx-orders::checkout.quick.title')" :description="__('webx-orders::checkout.quick.description')" sheet class="webx-orders-quick">
        <x-webx-form :action="$action" :csrf="false" :id="$id.'-form'" again class="webx-orders-quick__form">
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="ref" value="{{ $ref }}">
            <input type="hidden" name="qty" value="{{ max(1, (int) $qty) }}">
            @foreach (\Illuminate\Support\Arr::dot($options) as $option => $value)
                <input type="hidden" name="options[{{ str_replace('.', '][', (string) $option) }}]" value="{{ is_bool($value) ? (int) $value : $value }}">
            @endforeach
            @include('webx-orders::checkout.parts.honeypot', ['id' => $id.'-hp'])
            <input type="hidden" name="{{ \WebxUi\Orders\Checkout\CheckoutAntispam::TIMESTAMP }}" value="{{ app(\WebxUi\Orders\Checkout\CheckoutAntispam::class)->timestamp() }}">
            <x-webx-field type="tel" name="phone" :label="__('webx-orders::checkout.fields.phone')" :hint="__('webx-orders::checkout.fields.phone-hint')" required autocomplete="tel" />
            <x-webx-field name="name" :label="__('webx-orders::checkout.quick.name')" optional autocomplete="given-name" />
            @if ($terms === 'checkbox')
                <x-webx-field type="consent" name="terms" required>
                    {{ __('webx-orders::checkout.terms.agree') }}@foreach ($documents as $document){{ $loop->first ? ': ' : ', ' }}<a href="{{ $document['url'] }}" target="_blank" rel="noopener">{{ $document['title'] }}</a>@endforeach
                </x-webx-field>
            @elseif ($terms === 'notice')
                <p class="webx-orders-quick__terms">
                    {{ __('webx-orders::checkout.terms.notice') }}@foreach ($documents as $document){{ $loop->first ? ': ' : ', ' }}<a href="{{ $document['url'] }}" target="_blank" rel="noopener">{{ $document['title'] }}</a>@endforeach
                </p>
            @endif
            <x-webx-form.submit>{{ __('webx-orders::checkout.quick.submit') }}</x-webx-form.submit>
        </x-webx-form>
    </x-webx-dialog>
@endif
