{{--
    The field a person never sees and a robot fills in (spec §28): in the contacts and in the
    confirmation. Clipped inline rather than hidden by a stylesheet or `hidden` — the simpler robots
    skip what the page itself calls hidden; `aria-hidden` and `tabindex="-1"` keep it from a screen
    reader and out of the tab order.

    Classes: webx-orders-checkout__honeypot.
--}}
<div class="webx-orders-checkout__honeypot" aria-hidden="true" style="position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0">
    <label for="{{ $id }}">{{ __('webx-orders::checkout.fields.honeypot') }}</label>
    <input type="text" id="{{ $id }}" name="{{ \WebxUi\Orders\Checkout\CheckoutAntispam::HONEYPOT }}" value="" tabindex="-1" autocomplete="off">
</div>
