{{--
    The provider's widget, and the place for what the intake says about it.

    A checkbox — reCAPTCHA v2 or Turnstile — is drawn by the provider's own script: the div carries
    the class that script goes hunting for. An Invisible reCAPTCHA, a v3 one and an invisible
    Turnstile are run by inbox.js when the form is sent (`webx:form:submit`); without JavaScript
    they are refused, and told why. Every kind puts its answer into an input named
    `{{ $captcha['field'] }}` inside this form, and the intake verifies it with the site's secret.

    `data-webx-field="captcha"`: the intake files its word about the captcha under `captcha`, so
    the form's script puts it here — and so does the page that comes back without JavaScript.
--}}
<div
    class="webx-inbox-form__captcha"
    data-webx-field="captcha"
    data-webx-captcha="{{ $captcha['provider'] }}"
    data-webx-captcha-type="{{ $captcha['type'] }}"
    data-webx-captcha-key="{{ $captcha['key'] }}"
    data-webx-captcha-action="{{ $captcha['action'] }}"
    data-webx-captcha-message="{{ __('webx-inbox::errors.captcha') }}"
    data-webx-captcha-unavailable="{{ __('webx-inbox::errors.captcha-unavailable') }}"
>
    @if ($captcha['type'] === 'v3')
        <input type="hidden" name="{{ $captcha['field'] }}" value="">
    @else
        <div class="{{ $captcha['widget'] }}" data-sitekey="{{ $captcha['key'] }}"></div>
    @endif

    <p class="webx-field__error" id="{{ $formId }}-captcha-error" @if ($captchaErrors === []) hidden @endif>{{ implode(' ', $captchaErrors) }}</p>
</div>

@php($captchaScript = $assets->captchaScript($captcha['provider'], $captcha['type'] === 'v3' ? $captcha['key'] : null))
@if ($captchaScript !== null)
    <script src="{{ $captchaScript }}" async defer></script>
@endif
