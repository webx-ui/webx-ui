{{--
    The methods of the town (§12.3, decision 7): a radio a method — its icon, title, description,
    and the price on the right: an amount, «Free» with the old price crossed out above it, or «By
    carrier rates» with an estimate. A carrier's courier without streets in the town is shown and
    cannot be chosen, with its hint (§8.2). The chosen one opens in place: its point or its address.

    Classes: webx-delivery__methods, __methods-title, __method, __method-label, __radio, __icon,
    __method-text, __method-title, __method-description, __method-hint, __price, __price-was,
    __price-estimate, __method-body, __empty; is-chosen, is-disabled on a method;
    __price--<kind> (fixed, free, carrier_rates).
--}}
@props(['step'])
<fieldset class="webx-delivery__methods">
    <legend class="webx-delivery__methods-title">{{ __('webx-delivery::delivery.step.methods') }}</legend>
    @forelse ($step['methods'] as $method)
        @php($inputId = $step['id'].'-method-'.$method['id'])
        <div @class(['webx-delivery__method', 'is-chosen' => $method['chosen'], 'is-disabled' => $method['disabled']])>
            <label class="webx-delivery__method-label" for="{{ $inputId }}">
                <input class="webx-delivery__radio" type="radio" id="{{ $inputId }}" name="{{ ($step['field'])('method') }}" value="{{ $method['id'] }}" @checked($method['chosen']) @disabled($method['disabled']) @if ($method['hint'] !== null) aria-describedby="{{ $inputId }}-hint" @endif>
                @if ($method['icon'] !== null)
                    <img class="webx-delivery__icon" src="{{ $method['icon'] }}" alt="" width="32" height="32" loading="lazy">
                @endif
                <span class="webx-delivery__method-text">
                    <span class="webx-delivery__method-title">{{ $method['title'] }}</span>
                    @if ($method['description'] !== null)
                        <span class="webx-delivery__method-description">{{ $method['description'] }}</span>
                    @endif
                    @if ($method['hint'] !== null)
                        <span class="webx-delivery__method-hint" id="{{ $inputId }}-hint">{{ $method['hint'] }}</span>
                    @endif
                </span>
                @if ($method['price'] !== null)
                    <span class="webx-delivery__price webx-delivery__price--{{ $method['price']['kind'] }}">
                        @if ($method['price']['was'] !== null)
                            <s class="webx-delivery__price-was">{{ $method['price']['was'] }}</s>
                        @endif
                        {{ $method['price']['text'] }}
                        @if ($method['price']['estimate'] !== null)
                            <span class="webx-delivery__price-estimate">≈ {{ $method['price']['estimate'] }}</span>
                        @endif
                    </span>
                @endif
            </label>
            @if ($method['chosen'])
                <div class="webx-delivery__method-body">
                    @if ($method['asks'] === 'point' && $step['points'] !== null)
                        <x-webx-delivery::point-picker :step="$step" />
                    @elseif ($method['asks'] === 'address' && $step['address'] !== null)
                        <x-webx-delivery::address-form :step="$step" />
                    @endif
                </div>
            @endif
        </div>
    @empty
        <p class="webx-delivery__empty">{{ __('webx-delivery::delivery.step.no_methods') }}</p>
    @endforelse
    <p class="webx-delivery__error" data-delivery-error="method" @if (! isset($step['errors']['method'])) hidden @endif>{{ $step['errors']['method'] ?? '' }}</p>
</fieldset>
