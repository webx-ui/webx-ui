{{-- A letter rather than a line. The value goes between the tags with nothing around it: a
     newline after `<textarea>` is eaten by the parser, and one before `</textarea>` is not. --}}
<textarea
    class="wx-form__control"
    id="{{ $id }}"
    name="{{ $name }}"
    rows="{{ (int) ($field->option('rows') ?: 5) }}"
    @if ($placeholder !== '') placeholder="{{ $placeholder }}" @endif
    @if ($field->option('maxlength')) maxlength="{{ (int) $field->option('maxlength') }}" @endif
    @if ($field->is_required) required @endif
    @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
    @if ($messages !== []) aria-invalid="true" @endif
>{{ is_scalar($value) ? $value : '' }}</textarea>
