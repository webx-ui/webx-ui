{{--
    Several of several. The name ends in `[]`, so what arrives is an array.

    No `required` on the inputs, and that is not an oversight: a browser reads `required` on a
    checkbox as "this one must be ticked", not "one of these must be" — putting it on the group
    would demand every box. Whether enough boxes were ticked is the intake's answer (`min`,
    `max`), and it is the only place that can give it.
--}}
@php($chosen = array_map('strval', is_array($value) ? array_filter($value, 'is_scalar') : []))
<div
    class="wx-form__choices"
    role="group"
    @if ($labelledBy !== null) aria-labelledby="{{ $labelledBy }}" @endif
    @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
>
    @foreach ($field->choices() as $choiceValue => $choiceLabel)
        <label class="wx-form__choice" for="{{ $id }}-{{ $loop->index }}">
            <input
                type="checkbox"
                class="wx-form__choice-input"
                id="{{ $id }}-{{ $loop->index }}"
                name="{{ $name }}"
                value="{{ $choiceValue }}"
                @checked(in_array((string) $choiceValue, $chosen, true))
            >
            <span class="wx-form__choice-label">{{ $choiceLabel }}</span>
        </label>
    @endforeach
</div>
