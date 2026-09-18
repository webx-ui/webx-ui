{{-- The empty option is first and always there, even on a required field: a select that opens
     already answered collects that answer from everybody who did not read it. --}}
@php($chosen = is_scalar($value) ? (string) $value : '')
<select
    class="wx-form__control"
    id="{{ $id }}"
    name="{{ $name }}"
    @if ($field->is_required) required @endif
    @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
    @if ($messages !== []) aria-invalid="true" @endif
>
    <option value="">{{ $placeholder !== '' ? $placeholder : __('webx-inbox::form.choose') }}</option>
    @foreach ($field->choices() as $choiceValue => $choiceLabel)
        <option value="{{ $choiceValue }}" @selected($chosen === (string) $choiceValue)>{{ $choiceLabel }}</option>
    @endforeach
</select>
