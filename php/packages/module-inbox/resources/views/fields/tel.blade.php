{{-- The pattern is written the way HTML writes one — no delimiters, anchored by the browser —
     and the intake anchors and delimits the same string before it checks it. --}}
@php($pattern = $field->option('pattern'))
<input
    type="tel"
    class="wx-form__control"
    id="{{ $id }}"
    name="{{ $name }}"
    value="{{ is_scalar($value) ? $value : '' }}"
    autocomplete="tel"
    @if ($placeholder !== '') placeholder="{{ $placeholder }}" @endif
    @if (is_string($pattern) && trim($pattern) !== '') pattern="{{ $pattern }}" @endif
    @if ($field->is_required) required @endif
    @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
    @if ($messages !== []) aria-invalid="true" @endif
>
