{{--
    The one tick that says yes: terms, privacy, being written back to.

    Its words are the field's `text` option rather than its title, because they are a sentence
    with a link in it and not a caption — which is why they are printed raw. An administrator
    wrote them in the panel; the same trust the thank-you is printed with.
--}}
@php($text = $field->optionText('text', trim((string) $field->title)))
<label class="wx-form__consent" for="{{ $id }}">
    <input
        type="checkbox"
        class="wx-form__consent-input"
        id="{{ $id }}"
        name="{{ $name }}"
        value="1"
        @checked((bool) $value)
        @if ($field->is_required) required @endif
        @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
        @if ($messages !== []) aria-invalid="true" @endif
    >
    <span class="wx-form__consent-text">{!! $text !!}</span>
</label>
