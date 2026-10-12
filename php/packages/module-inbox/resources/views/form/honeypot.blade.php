{{--
    The field a person never sees and a robot fills in (§7).

    Clipped inline rather than hidden by a stylesheet or `hidden`: a honeypot that shows is a form
    asking a nonsense question, and the simpler robots skip what the page itself calls hidden.
    `aria-hidden` and `tabindex="-1"` keep it from a screen reader and out of the tab order. Not an
    <x-webx-field>: nothing of a field — a label shown, an error, a check — is wanted here.
--}}
<div class="webx-inbox-form__honeypot" aria-hidden="true" style="position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0">
    <label for="{{ $id }}">{{ __('webx-inbox::form.honeypot') }}</label>
    <input type="text" id="{{ $id }}" name="{{ $name }}" value="" tabindex="-1" autocomplete="off">
</div>
