{{--
    The field a person never sees and a robot fills in (§7).

    The inline style is the one place this package writes any, and it is not decoration: a
    honeypot that is visible is a form asking a nonsense question, and the package ships no
    stylesheet to hide it with. Clipped rather than `display: none` or `hidden`, because the
    simpler robots skip what the page itself calls hidden.

    `aria-hidden` and `tabindex="-1"` keep it away from a screen reader and out of the tab
    order, so the only visitor who ever meets it is the one this is for.
--}}
<div class="wx-form__honeypot" aria-hidden="true" style="position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0">
    <label for="{{ $name }}">{{ __('webx-inbox::form.honeypot') }}</label>
    <input type="text" id="{{ $name }}" name="{{ $name }}" value="" tabindex="-1" autocomplete="off">
</div>
