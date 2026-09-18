{{-- One of several. `required` goes on every input of the group, which is what the browser
     expects: it treats the group as answered as soon as any one of them is. --}}
@php($chosen = is_scalar($value) ? (string) $value : '')
<div
    class="wx-form__choices"
    role="radiogroup"
    @if ($labelledBy !== null) aria-labelledby="{{ $labelledBy }}" @endif
    @if ($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
>
    @foreach ($field->choices() as $choiceValue => $choiceLabel)
        <label class="wx-form__choice" for="{{ $id }}-{{ $loop->index }}">
            <input
                type="radio"
                class="wx-form__choice-input"
                id="{{ $id }}-{{ $loop->index }}"
                name="{{ $name }}"
                value="{{ $choiceValue }}"
                @checked($chosen === (string) $choiceValue)
                @if ($field->is_required) required @endif
            >
            <span class="wx-form__choice-label">{{ $choiceLabel }}</span>
        </label>
    @endforeach
</div>
