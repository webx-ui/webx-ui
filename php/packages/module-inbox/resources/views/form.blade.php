{{--
    The frame of a form on the site (§10).

    This is the least markup a form can be and still work: no framework, no classes anybody
    else's stylesheet knows, no colours and no spacing. The site publishes these views
    (`php artisan vendor:publish --tag=webx-inbox-views`) and rewrites them, and from that
    moment they belong to the site — which is exactly what the reference implementation did,
    keeping the intake and replacing every control.

    What must survive a rewrite is small and named here so it is not lost by accident:

      · the `webx_form` marker — a page may carry two forms, and it is how one of them knows
        that what came back in the session is its own,
      · the honeypot and the timestamp — the antispam that costs the visitor nothing,
      · `data-webx-*` — what the enhancement script looks for; drop them and the form still
        works, it just reloads the page to say so.
--}}
@php
    $formId = 'wx-form-'.$form->slug;
@endphp
<form
    method="post"
    action="{{ $action }}"
    @if ($multipart) enctype="multipart/form-data" @endif
    data-webx-form="{{ $form->slug }}"
    {{ $attributes->merge(['class' => 'wx-form']) }}
>
    <input type="hidden" name="webx_form" value="{{ $form->slug }}">

    {{-- The page the form stands on. The referrer of a POST usually says the same thing, and
         usually is not what a browser that was told to send no referrer sends. --}}
    <input type="hidden" name="webx_page" value="{{ $page }}">

    <input type="hidden" name="{{ $timestampField }}" value="{{ $timestamp }}">

    @includeWhen($honeypot !== null, 'webx-inbox::honeypot', ['name' => $honeypot])

    <div class="wx-form__fields">
        @foreach ($fields as $field)
            @include('webx-inbox::field', [
                'field' => $field,
                'id' => $formId.'-'.$field->key(),
                'name' => 'fields['.$field->key().']'.($field->isMultiple() ? '[]' : ''),
                'value' => $values[$field->key()] ?? null,
                'messages' => $invalid['fields.'.$field->key()] ?? [],
            ])
        @endforeach
    </div>

    @includeWhen($captcha !== null, 'webx-inbox::captcha', ['captcha' => $captcha])

    @include('webx-inbox::message')

    <div class="wx-form__actions">
        <button
            type="submit"
            class="wx-form__submit"
            data-webx-submit
            data-busy="{{ __('webx-inbox::form.sending') }}"
        >{{ $submitText }}</button>
    </div>

    @php($script = $assets->script())
    @if ($script !== null)
        <script src="{{ $script }}" defer></script>
    @endif
</form>
