{{--
    The point of the chosen method (§12.2–§12.3): a list with a search — the first 20 points of the
    town loaded, the rest by the server's search inside the town, «Show more» for the next page;
    without JavaScript a plain select of the first page. Under it the chosen point: its address,
    today's hours, who alone may use it (a locker for the residents of a block) and the phone.

    Classes: webx-delivery__point, __point-card, __point-title, __point-address, __point-today,
    __point-restricted, __point-phone, __error.
--}}
@props(['step'])
@php($points = $step['points'])
@php($fieldId = $step['id'].'-point')
<div class="webx-delivery__point">
    <x-webx-select
        :id="$fieldId"
        :name="($step['field'])('point')"
        :options="$points['options']"
        :value="$points['value']"
        searchable
        :source="$points['source']"
        :min="1"
        :label="__('webx-delivery::delivery.step.choose_point')"
        :show-label="true"
        :placeholder="__('webx-delivery::delivery.step.choose_point')"
        data-delivery-point
    />
    <p class="webx-delivery__error" data-delivery-error="point" @if (! isset($step['errors']['point'])) hidden @endif>{{ $step['errors']['point'] ?? '' }}</p>
    @if ($step['point'] !== null)
        <div class="webx-delivery__point-card">
            <p class="webx-delivery__point-title">{{ $step['point']['title'] }}</p>
            <p class="webx-delivery__point-address">{{ $step['point']['address'] }}</p>
            @if ($step['point']['today'] !== null)
                <p class="webx-delivery__point-today">{{ $step['point']['today'] }}</p>
            @endif
            @if (($step['point']['restricted'] ?? null) !== null)
                <p class="webx-delivery__point-restricted">{{ $step['point']['restricted'] }}</p>
            @endif
            @if ($step['point']['phone'] !== null)
                <p class="webx-delivery__point-phone"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $step['point']['phone']) }}">{{ $step['point']['phone'] }}</a></p>
            @endif
        </div>
    @endif
</div>
