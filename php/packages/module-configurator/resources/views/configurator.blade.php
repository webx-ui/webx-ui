{{--
    A configurator on the site (§12.2): `<x-webx-configurator key="…" />`.

    Everything is printed here and the widget only switches (decision 9): every step, option and
    field — what is not offered at the start with `hidden`, its inputs `disabled` so they are not
    sent — the price of the start with its breakdown, «from N» on the cards, the picture. Without
    JavaScript the radios and the sizes still change and are sent with the request; the price is
    counted by the server when it arrives (§12.5, §20.7).

    The radios and the sizes belong to the request form in the dialog by `form="{{ $formId }}"`,
    and so do the two hidden inputs that name the enclosure (§11.2): the visitor's contacts and the
    configuration go together in one POST, and the server prices it again — no price is sent.

    Override: <layer>/views/vendor/webx-configurator/configurator.blade.php — keep
    `data-webx-configurator`, the `data-webx-configurator-*` marks, the names of the inputs and the
    `<script type="application/json">` the widget reads. Classes: `### Classes` in AGENTS.md.
--}}
@php
    $owner = $formId !== null ? ' form="'.e($formId).'"' : '';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
@endphp
<section class="webx-configurator" id="{{ $id }}" data-webx-configurator="{{ $key }}"@if ($form) data-webx-configurator-form="{{ $form }}"@endif>
    @if ($title)
        <h2 class="webx-configurator__title">{{ $title }}</h2>
    @endif

    <div class="webx-configurator__layout">
        <figure class="webx-configurator__preview" data-webx-configurator-preview @if (! $preview) hidden @endif>
            <img class="webx-configurator__preview-image" src="{{ $preview['src'] ?? '' }}"@if ($preview['srcset'] ?? null) srcset="{{ $preview['srcset'] }}" sizes="(min-width: 60rem) 24rem, 100vw"@endif alt="{{ $preview['alt'] ?? '' }}"@if ($preview['width'] ?? null) width="{{ $preview['width'] }}" height="{{ $preview['height'] }}"@endif>
        </figure>

        <div class="webx-configurator__steps">
            @foreach ($steps as $step)
                <fieldset class="webx-configurator__step webx-configurator__step--{{ $step['type'] }}" data-webx-configurator-step="{{ $step['key'] }}" @if ($step['hidden']) hidden @endif>
                    <legend class="webx-configurator__step-title">{{ $step['title'] }}</legend>
                    @if ($step['hint'] !== '')
                        <p class="webx-configurator__hint">{{ $step['hint'] }}</p>
                    @endif

                    @if ($step['type'] === 'dimensions')
                        <div class="webx-configurator__fields">
                            @foreach ($step['fields'] as $field)
                                @php($input = $id.'-'.$field['key'])
                                <div class="webx-configurator__field" data-webx-configurator-field="{{ $field['key'] }}" @if ($field['hidden']) hidden @endif>
                                    <label class="webx-configurator__field-label" for="{{ $input }}">{{ $field['label'] }}</label>
                                    <div class="webx-configurator__stepper">
                                        <button type="button" class="webx-configurator__stepper-button" data-webx-configurator-by="-1" aria-label="{{ __('webx-configurator::site.decrease', ['field' => $field['label']]) }}" aria-controls="{{ $input }}" hidden>&minus;</button>
                                        <input class="webx-configurator__input" type="number" inputmode="decimal" id="{{ $input }}" name="configurator[values][{{ $field['key'] }}]" value="{{ $field['value'] }}" min="{{ $field['min'] }}" max="{{ $field['max'] }}" step="{{ $field['step'] }}"{!! $owner !!} @if ($field['hidden']) disabled @endif>
                                        <button type="button" class="webx-configurator__stepper-button" data-webx-configurator-by="1" aria-label="{{ __('webx-configurator::site.increase', ['field' => $field['label']]) }}" aria-controls="{{ $input }}" hidden>+</button>
                                    </div>
                                    <p class="webx-configurator__range" data-webx-configurator-range>
                                        <span data-webx-configurator-min>{{ $field['min'] }}</span>–<span data-webx-configurator-max>{{ $field['max'] }}</span>@if ($field['unit'] !== '') {{ $field['unit'] }}@endif
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="webx-configurator__options webx-configurator__options--{{ $step['type'] }}">
                            @foreach ($step['options'] as $option)
                                <label @class([
                                    'webx-configurator__option',
                                    'webx-configurator__option--card' => $step['type'] === 'cards',
                                    'webx-configurator__option--swatch' => $step['type'] === 'swatches',
                                    'webx-configurator__option--button' => $step['type'] === 'buttons',
                                    'is-selected' => $option['selected'],
                                ]) data-webx-configurator-option="{{ $option['value'] }}" @if ($option['hidden']) hidden @endif>
                                    <input class="webx-configurator__radio" type="radio" name="configurator[values][{{ $step['key'] }}]" value="{{ $option['value'] }}"{!! $owner !!} @checked($option['selected']) @disabled($option['hidden'])>
                                    @if ($step['type'] === 'cards' && $option['image'])
                                        <img class="webx-configurator__option-image" src="{{ $option['image']['src'] }}"@if ($option['image']['srcset']) srcset="{{ $option['image']['srcset'] }}" sizes="12rem"@endif alt=""@if ($option['image']['width']) width="{{ $option['image']['width'] }}" height="{{ $option['image']['height'] }}"@endif loading="lazy">
                                    @elseif ($step['type'] === 'swatches')
                                        {{-- A colour or a gradient of the white list (§5.5), or a picture: data, not the module's CSS. --}}
                                        <span class="webx-configurator__swatch" aria-hidden="true"@if ($option['swatchImage']) style="--webx-configurator-swatch: url('{{ $option['swatchImage'] }}') center / cover"@elseif ($option['swatch']) style="--webx-configurator-swatch: {{ $option['swatch'] }}"@endif></span>
                                    @endif
                                    <span class="webx-configurator__option-text">
                                        <span class="webx-configurator__option-label">{{ $option['label'] }}</span>
                                        @if ($option['subtitle'] !== '')
                                            <span class="webx-configurator__option-subtitle">{{ $option['subtitle'] }}</span>
                                        @endif
                                        @if ($option['from'] !== null)
                                            <span class="webx-configurator__option-from">{{ __('webx-configurator::site.from', ['price' => $option['from']]) }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </fieldset>
            @endforeach
        </div>

        <div class="webx-configurator__summary">
            <p class="webx-configurator__price" aria-live="polite">
                <span class="webx-configurator__discount" data-webx-configurator-discount @if (! $price['discount']) hidden @endif>{{ $price['discount'] }}</span>
                <s class="webx-configurator__old-price" data-webx-configurator-before @if (! $price['before']) hidden @endif><span class="webx-configurator__sr">{{ __('webx-configurator::site.old-price') }}</span> <span data-webx-configurator-before-value>{{ $price['before'] }}</span></s>
                <span class="webx-configurator__total" data-webx-configurator-total>{{ $price['total'] }}</span>
            </p>

            <details class="webx-configurator__breakdown">
                <summary class="webx-configurator__breakdown-title">{{ __('webx-configurator::site.breakdown') }}</summary>
                <ul class="webx-configurator__lines" data-webx-configurator-lines>
                    @foreach ($price['lines'] as $line)
                        <li @class(['webx-configurator__line', 'is-discount' => $line['kind'] === 'discount'])><span class="webx-configurator__line-text">{{ $line['text'] }}</span> <span class="webx-configurator__line-amount">{{ $line['amount'] }}</span></li>
                    @endforeach
                </ul>
                <p class="webx-configurator__note">
                    <span data-webx-configurator-area @if (! $price['area']) hidden @endif>{{ __('webx-configurator::site.area') }}: <span data-webx-configurator-area-value>{{ $price['area'] }}</span> m²</span>
                    @if ($price['rounded'])
                        <span>{{ __('webx-configurator::site.rounded', ['step' => $price['rounded']]) }}</span>
                    @endif
                </p>
            </details>

            @if ($form || $messengers)
                <div class="webx-configurator__actions">
                    @if ($form)
                        {{-- An opener of the form in a dialog (WIDGETS §6.4): the server finds it and prints the dialog; without JavaScript, `:target` shows it. --}}
                        <a class="webx-configurator__submit" href="#webx-form-{{ $form }}" data-webx-form="{{ $form }}" data-webx-sheet>{{ __('webx-configurator::site.submit') }}</a>
                    @endif
                    @foreach ($messengers as $messenger)
                        <a class="webx-configurator__messenger webx-configurator__messenger--{{ $messenger['kind'] }}" href="{{ $messenger['url'] }}" target="_blank" rel="noopener" title="{{ __('webx-configurator::site.write', ['messenger' => $messenger['label']]) }}">
                            @if ($messenger['icon'])
                                <svg class="webx-configurator__messenger-icon" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false">@include($messenger['icon'])</svg>
                            @endif
                            <span class="webx-configurator__sr">{{ __('webx-configurator::site.write', ['messenger' => $messenger['label']]) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($form)
        <input type="hidden" name="webx_enclosure[]" value="configurator"{!! $owner !!}>
        <input type="hidden" name="configurator[key]" value="{{ $key }}"{!! $owner !!}>
        {{-- What the widget puts over the fields of the dialog when it opens: what was chosen and what it costs (§11.2), only to read. --}}
        <template data-webx-configurator-recap>
            <div class="webx-configurator__recap">
                <p class="webx-configurator__recap-title">{{ $title ?? __('webx-configurator::site.chosen') }}</p>
                <ul class="webx-configurator__recap-list"></ul>
                <p class="webx-configurator__recap-total"></p>
            </div>
        </template>
    @endif

    <script type="application/json" data-webx-configurator-data>{!! $json !!}</script>
</section>
@if ($script)
    <script type="module" src="{{ $script }}"></script>
@endif
