{{--
    The town (decision 40). Inside the step's form: the card of the chosen town «Town D, Region B ·
    Change» — «Choose a city or town» while there is none — and, without JavaScript, the field of
    the town with a list to start from and «Show delivery methods». With `dialog`, after the form:
    the dialog «Where to deliver the order?» with the search of the server and the popular towns,
    which «Change» opens once the script runs.

    Classes: webx-delivery__locality, __card, __card-label, __card-value, __change,
    __locality-form, __popular, __popular-title, __popular-list, __popular-item, __town, __search, __nojs,
    __error; is-empty on the card while no town is chosen.
--}}
@props(['step', 'dialog' => false])
@php($dialogId = $step['id'].'-where')
@php($fieldId = $step['id'].'-locality')
@if (! $dialog)
    <div class="webx-delivery__locality">
        <p @class(['webx-delivery__card', 'is-empty' => $step['locality'] === null])>
            <span class="webx-delivery__card-label">{{ __('webx-delivery::delivery.step.locality') }}</span>
            <span class="webx-delivery__card-value" data-delivery-locality-label>{{ $step['locality']['label'] ?? __('webx-delivery::delivery.step.choose_locality') }}</span>
            <a class="webx-delivery__change" href="#{{ $fieldId }}" data-webx-dialog="{{ $dialogId }}" aria-haspopup="dialog">{{ $step['locality'] === null ? __('webx-delivery::delivery.step.choose') : __('webx-delivery::delivery.step.change') }}</a>
        </p>
        <div class="webx-delivery__locality-form webx-delivery__nojs">
            <label class="webx-delivery__label" for="{{ $fieldId }}">{{ __('webx-delivery::delivery.step.locality') }}</label>
            <input class="webx-delivery__control" type="text" id="{{ $fieldId }}" name="{{ ($step['field'])('locality_name') }}" value="{{ $step['localityValue'] }}" list="{{ $fieldId }}-list" placeholder="{{ __('webx-delivery::delivery.step.locality_placeholder') }}" autocomplete="off" @if (isset($step['errors']['locality'])) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif>
            <datalist id="{{ $fieldId }}-list">
                @foreach ($step['datalist'] as $town)
                    <option value="{{ $town['name'] }}">{{ $town['label'] }}</option>
                @endforeach
            </datalist>
            <button type="submit" class="webx-delivery__show">{{ __('webx-delivery::delivery.step.show_methods') }}</button>
        </div>
        <p class="webx-delivery__error" id="{{ $fieldId }}-error" data-delivery-error="locality" @if (! isset($step['errors']['locality'])) hidden @endif>{{ $step['errors']['locality'] ?? '' }}</p>
        @isset($step['errors']['country'])
            <p class="webx-delivery__error" data-delivery-error="country">{{ $step['errors']['country'] }}</p>
        @endisset
    </div>
@else
    <x-webx-dialog :id="$dialogId" :title="__('webx-delivery::delivery.step.where')" sheet class="webx-delivery__dialog">
        <div class="webx-delivery__search">
            @if ($step['urls']['localities'] !== null)
                <x-webx-select
                    name="locality"
                    searchable
                    :source="$step['urls']['localities']"
                    inline
                    :label="__('webx-delivery::delivery.step.locality')"
                    :show-label="true"
                    :placeholder="__('webx-delivery::delivery.step.locality_placeholder')"
                    data-delivery-locality
                />
            @endif
        </div>
        @if ($step['popular'] !== [])
            <div class="webx-delivery__popular">
                <p class="webx-delivery__popular-title">{{ __('webx-delivery::delivery.step.popular') }}</p>
                <ul class="webx-delivery__popular-list">
                    @foreach ($step['popular'] as $town)
                        <li class="webx-delivery__popular-item">
                            <button type="button" class="webx-delivery__town" data-delivery-town="{{ $town['id'] }}" title="{{ $town['label'] }}" @if (($step['locality']['id'] ?? null) === $town['id']) aria-pressed="true" @endif>{{ $town['name'] }}</button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-webx-dialog>
@endif
