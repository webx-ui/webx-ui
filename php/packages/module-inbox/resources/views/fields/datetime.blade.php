{{-- Before W6.4 (an alias period): a day and a time for a view a site published before the engine.
     The plain controls of FORM_STEPS §6.4 without the calendar — the intake checks the grid, the day
     of the week and the past in the site's zone either way. --}}
@php
    $zone = \WebxUi\Inbox\Fields\DateTimeField::zone();
    $chosen = is_array($value) ? $value : [];
@endphp
<input
    type="date"
    class="wx-form__control"
    id="{{ $id }}"
    name="{{ $name }}[date]"
    value="{{ is_string($chosen['date'] ?? null) ? $chosen['date'] : '' }}"
    min="{{ now($zone)->format('Y-m-d') }}"
    @if ($field->is_required) required @endif
    @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
    @if ($messages !== []) aria-invalid="true" @endif
>
<select
    class="wx-form__control"
    name="{{ $name }}[time]"
    aria-label="{{ __('webx-widgets::widgets.datetime.time') }}"
    @if ($field->is_required) required @endif
>
    <option value="">{{ __('webx-widgets::widgets.datetime.time') }}</option>
    @foreach (\WebxUi\Inbox\Fields\DateTimeField::times($field->options) as $time)
        <option value="{{ $time }}" @selected(($chosen['time'] ?? null) === $time)>{{ $time }}</option>
    @endforeach
</select>
<p class="wx-form__help">{{ __('webx-widgets::widgets.datetime.zone', ['zone' => \WebxUi\Inbox\Fields\DateTimeField::zoneName($zone)]) }}</p>
