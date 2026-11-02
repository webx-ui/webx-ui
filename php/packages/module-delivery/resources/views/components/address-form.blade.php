{{--
    The address of a courier (§8.2, §8.3). The street of a carrier's courier is one of its
    directory — a list with the server's search, nothing typed by hand (decision 31; without
    JavaScript a select of its first streets); the street of the shop's courier is text, with the
    carriers' streets of the town as suggestions when there are any. The house, the apartment and
    the note — text for both.

    Classes: webx-delivery__address, __field, __field--<name> (street, house, apartment, note),
    __label, __control, __error.
--}}
@props(['step'])
@php($address = $step['address'])
@php($id = $step['id'].'-address')
@php($error = static fn (string $key): ?string => $step['errors']['address.'.$key] ?? null)
<div class="webx-delivery__address" data-delivery-address>
    <div class="webx-delivery__field webx-delivery__field--street">
        @if ($address['strict'])
            <x-webx-select
                :id="$id.'-street'"
                :name="($step['field'])('address.street_id')"
                :options="$address['streets']"
                :value="$address['street_id']"
                searchable
                :source="$address['source']"
                :min="0"
                :label="__('webx-delivery::delivery.step.street')"
                :show-label="true"
                :placeholder="__('webx-delivery::delivery.step.choose_street')"
                required
            />
        @else
            <x-webx-select
                :id="$id.'-street'"
                :name="($step['field'])('address.street')"
                :strict="false"
                :value="$address['street']"
                :source="$address['source']"
                :min="2"
                :label="__('webx-delivery::delivery.step.street')"
                :show-label="true"
                :placeholder="__('webx-delivery::delivery.step.street')"
                required
            />
        @endif
        <p class="webx-delivery__error" data-delivery-error="address.street" @if ($error('street') === null) hidden @endif>{{ $error('street') }}</p>
    </div>
    @foreach (['house' => 32, 'apartment' => 32] as $part => $max)
        <div class="webx-delivery__field webx-delivery__field--{{ $part }}">
            <label class="webx-delivery__label" for="{{ $id }}-{{ $part }}">{{ __('webx-delivery::delivery.step.'.$part) }}</label>
            <input class="webx-delivery__control" type="text" id="{{ $id }}-{{ $part }}" name="{{ ($step['field'])('address.'.$part) }}" value="{{ $address[$part] }}" maxlength="{{ $max }}" @if ($part === 'house') required autocomplete="address-line2" @endif @if ($error($part) !== null) aria-invalid="true" @endif>
            <p class="webx-delivery__error" data-delivery-error="address.{{ $part }}" @if ($error($part) === null) hidden @endif>{{ $error($part) }}</p>
        </div>
    @endforeach
    <div class="webx-delivery__field webx-delivery__field--note">
        <label class="webx-delivery__label" for="{{ $id }}-note">{{ __('webx-delivery::delivery.step.note') }}</label>
        <textarea class="webx-delivery__control" id="{{ $id }}-note" name="{{ ($step['field'])('address.note') }}" rows="2" maxlength="500">{{ $address['note'] }}</textarea>
        <p class="webx-delivery__error" data-delivery-error="address.note" @if ($error('note') === null) hidden @endif>{{ $error('note') }}</p>
    </div>
</div>
