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
      · `webx_locale` — the intake stands outside the site's own middleware, so the language
        it answers in is the one this says the page was printed in,
      · `webx_placement` — where on the site the form stood (`placement="footer"`), so the
        panel can tell a subscription from the footer from one out of an article,
      · the honeypot and the timestamp — the antispam that costs the visitor nothing,
      · `data-webx-*` — what the enhancement script looks for; drop them and the form still
        works, it just reloads the page to say so.
--}}
@php
    // Unique on the page even when the same form stands on it twice. A view published before
    // the component handed it in falls back to the old, plain name.
    $formId ??= 'wx-form-'.$form->slug;
@endphp
<form
    method="post"
    action="{{ $action }}"
    @if ($multipart) enctype="multipart/form-data" @endif
    data-webx-form="{{ $form->slug }}"
    {{ $attributes->merge(['class' => 'wx-form'.($placement === null ? '' : ' wx-form--'.$placement)]) }}
>
    <input type="hidden" name="webx_form" value="{{ $form->slug }}">

    {{-- The page the form stands on. The referrer of a POST usually says the same thing, and
         usually is not what a browser that was told to send no referrer sends. --}}
    <input type="hidden" name="webx_page" value="{{ $page }}">

    {{-- The language this page is printed in. The intake's stack is written out by hand and so
         carries nothing the site added to its `web` group, the language included. --}}
    <input type="hidden" name="webx_locale" value="{{ $locale }}">

    {{-- Where on the site this form stands, when the page said so. --}}
    @if ($placement !== null)
        <input type="hidden" name="webx_placement" value="{{ $placement }}">
    @endif

    <input type="hidden" name="{{ $timestampField }}" value="{{ $timestamp }}">

    @includeWhen($honeypot !== null, 'webx-inbox::honeypot', ['name' => $honeypot, 'id' => $formId.'-hp'])

    <div class="wx-form__fields">
        @foreach ($fields as $field)
            @include('webx-inbox::field', [
                'field' => $field,
                'id' => $formId.'-'.$field->key(),
                'name' => 'fields['.$field->key().']'.($field->isMultiple() ? '[]' : ''),
                'value' => $values[$field->key()] ?? null,
                'messages' => $invalid['fields.'.$field->key()] ?? [],
                'placement' => $placement,
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
