{{--
    One field: the label, the control, the hint and the place an error goes.

    The control itself is a partial per type (`fields/<type>.blade.php`), so a site that wants
    its own text input rewrites one small file and keeps the rest.
--}}
@php
    $type = $field->type->value;
    $label = trim((string) $field->title);
    $help = trim((string) $field->help);
    $placeholder = trim((string) $field->placeholder);

    // A group of ticks or dots has no single control to point a `<label for>` at, so its
    // caption is a plain span and the group names it instead.
    $grouped = in_array($type, ['radio', 'checkbox'], true);

    $control = [
        'field' => $field,
        'id' => $id,
        'name' => $name,
        'value' => $value,
        'placeholder' => $placeholder,
        'messages' => $messages,
        'describedBy' => $help === '' ? null : $id.'-help',
        'labelledBy' => $grouped && $label !== '' ? $id.'-label' : null,
        'accept' => $type === 'file' ? '.'.implode(',.', $files->extensions($field)) : null,
    ];
@endphp

@if ($type === 'hidden')
    @include('webx-inbox::fields.hidden', $control)
@else
    <div
        @class(['wx-form__field', 'wx-form__field--'.$type, 'is-invalid' => $messages !== []])
        data-webx-field="{{ $field->key() }}"
    >
        @if ($label !== '' && $type !== 'consent')
            @if ($grouped)
                <span class="wx-form__label" id="{{ $id }}-label">{{ $label }}@if ($field->is_required)<abbr class="wx-form__required" title="{{ __('webx-inbox::form.required') }}">*</abbr>@endif</span>
            @else
                <label class="wx-form__label" for="{{ $id }}">{{ $label }}@if ($field->is_required)<abbr class="wx-form__required" title="{{ __('webx-inbox::form.required') }}">*</abbr>@endif</label>
            @endif
        @endif

        @include('webx-inbox::fields.'.$type, $control)

        @if ($help !== '')
            <p class="wx-form__help" id="{{ $id }}-help">{{ $help }}</p>
        @endif

        {{-- Always here, and empty until there is something to say: the script writes into it
             rather than building it, so a published view decides where an error appears. --}}
        <p class="wx-form__error" data-webx-error role="alert" @if ($messages === []) hidden @endif>{{ implode(' ', $messages) }}</p>
    </div>
@endif
