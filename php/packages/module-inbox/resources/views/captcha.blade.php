{{--
    The provider's widget, drawn by the provider's own script.

    The div carries the class that script goes hunting for, so nothing here has to know how
    either provider works — and the script is printed once per page beside the first widget
    that needs it. A site that loads it itself empties `webx-inbox.captcha.<provider>.script`.

    Both providers put their answer into a hidden input of their own inside this form; the
    intake reads it by name and verifies it with the site's secret. A form that asks for a
    captcha the site has no site key for prints nothing here and says so in the log — the form
    would refuse every submission anyway, and a line somebody can find beats a mystery.
--}}
<div class="wx-form__captcha" data-webx-captcha="{{ $captcha['provider'] }}">
    <div class="{{ $captcha['widget'] }}" data-sitekey="{{ $captcha['key'] }}"></div>
</div>

@php($captchaScript = $assets->captchaScript($captcha['provider']))
@if ($captchaScript !== null)
    <script src="{{ $captchaScript }}" async defer></script>
@endif
