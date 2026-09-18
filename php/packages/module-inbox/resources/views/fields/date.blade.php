{{-- The bounds are ISO dates, the way `input[type=date]` wants them and the way the intake
     reads them; a browser without a date picker falls back to a text box and the intake is
     then the only thing checking, which it was anyway. --}}
@php($min = $field->option('min'))
@php($max = $field->option('max'))
<input
    type="date"
    class="wx-form__control"
    id="{{ $id }}"
    name="{{ $name }}"
    value="{{ is_scalar($value) ? $value : '' }}"
    @if (is_string($min) && $min !== '') min="{{ $min }}" @endif
    @if (is_string($max) && $max !== '') max="{{ $max }}" @endif
    @if ($field->is_required) required @endif
    @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
    @if ($messages !== []) aria-invalid="true" @endif
>
