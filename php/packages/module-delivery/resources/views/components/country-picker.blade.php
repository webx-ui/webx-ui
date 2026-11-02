{{--
    The country of delivery (decision 17): not printed while the shop delivers to one country —
    the step knows it then. Changing it asks for the town again.

    Classes: webx-delivery__country, webx-delivery__label, webx-delivery__control.
--}}
@props(['step'])
@if ($step['showCountry'])
    <div class="webx-delivery__country">
        <label class="webx-delivery__label" for="{{ $step['id'] }}-country">{{ __('webx-delivery::delivery.step.country') }}</label>
        <select class="webx-delivery__control" id="{{ $step['id'] }}-country" name="{{ ($step['field'])('country') }}" data-delivery-country>
            @foreach ($step['countries'] as $country)
                <option value="{{ $country['code'] }}" @selected($country['code'] === $step['country'])>{{ $country['name'] }}</option>
            @endforeach
        </select>
    </div>
@elseif ($step['country'] !== null)
    <input type="hidden" name="{{ ($step['field'])('country') }}" value="{{ $step['country'] }}">
@endif
