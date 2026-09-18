{{--
    An attachment, or several.

    `accept` is a hint to the file picker and nothing more — it is written in the extensions
    the module allows, and the intake checks the file's real type rather than its name. There
    is no value to put back: a browser will not let a page decide what file is selected, so a
    submission that comes back with errors comes back with the file gone. The visitor is told
    so by the hint below, which is the honest thing to do about a limitation nobody can fix.
--}}
<input
    type="file"
    class="wx-form__control wx-form__control--file"
    id="{{ $id }}"
    name="{{ $name }}"
    @if ($field->isMultiple()) multiple @endif
    @if ($accept !== null && $accept !== '.') accept="{{ $accept }}" @endif
    @if ($field->is_required) required @endif
    @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
    @if ($messages !== []) aria-invalid="true" @endif
>
