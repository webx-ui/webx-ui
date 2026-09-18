{{-- A line of text. `maxlength` is the field's own, and the intake checks the same number. --}}
<input
    type="text"
    class="wx-form__control"
    id="{{ $id }}"
    name="{{ $name }}"
    value="{{ is_scalar($value) ? $value : '' }}"
    @if ($placeholder !== '') placeholder="{{ $placeholder }}" @endif
    @if ($field->option('maxlength')) maxlength="{{ (int) $field->option('maxlength') }}" @endif
    @if ($field->is_required) required @endif
    @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
    @if ($messages !== []) aria-invalid="true" @endif
>
