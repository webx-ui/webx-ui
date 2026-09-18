{{-- `type=email` for the keyboard a phone puts up, and `autocomplete` so a browser can fill
     it: this is the field a visitor types most often and mistypes most often. --}}
<input
    type="email"
    class="wx-form__control"
    id="{{ $id }}"
    name="{{ $name }}"
    value="{{ is_scalar($value) ? $value : '' }}"
    autocomplete="email"
    @if ($placeholder !== '') placeholder="{{ $placeholder }}" @endif
    @if ($field->is_required) required @endif
    @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
    @if ($messages !== []) aria-invalid="true" @endif
>
