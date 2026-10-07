{{--
    The provider's widget, and the box for what the intake says about it.

    A checkbox — reCAPTCHA v2 or Turnstile — is drawn by the provider's own script: the div
    carries the class that script goes hunting for, so nothing here has to know how either
    provider works. An Invisible reCAPTCHA and a v3 one are run by the form's script when the
    form is sent (`webx-inbox.captcha.recaptcha.type` says which the site's keys are), so they
    need it; without JavaScript they are refused, and told why.

    The provider's script is printed once per page beside the first widget that needs it; a v3
    one carries the site key, because that is how v3 is loaded. A site that loads it itself
    empties `webx-inbox.captcha.<provider>.script`.

    Every kind puts its answer into an input named `{{ $captcha['field'] }}` inside this form;
    the intake reads it by name and verifies it with the site's secret. A form that asks for a
    captcha the site has no site key for prints nothing here and says so in the log — the form
    would refuse every submission anyway, and a line somebody can find beats a mystery.
--}}
@php($captchaRefusals = $invalid['captcha'] ?? [])
<div
    class="wx-form__captcha"
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

    <p
        class="wx-form__error"
        data-webx-captcha-error
        role="alert"
        @if ($captchaRefusals === []) hidden @endif
    >{{ implode(' ', $captchaRefusals) }}</p>
</div>

@php($captchaScript = $assets->captchaScript($captcha['provider'], $captcha['type'] === 'v3' ? $captcha['key'] : null))
@if ($captchaScript !== null)
    <script src="{{ $captchaScript }}" async defer></script>
@endif
