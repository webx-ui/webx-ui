{{--
    «To pay · Confirm» at the bottom of a narrow container (spec §20.1), where the column went
    under the steps: the amount of the column and a button that sends its form (`form=`), so it
    works without JavaScript too. The script copies the amount after every redraw of the column,
    hides the row while the column's own button is in sight or a field has the focus (the
    keyboard of a phone). A wide container never shows it.

    Classes: webx-orders-checkout__sticky, __sticky-payable, __sticky-place.
--}}
<div class="webx-orders-checkout__sticky">
    <p class="webx-orders-checkout__sticky-payable">
        <span>{{ __('webx-orders::checkout.summary.payable') }}</span>
        <strong data-payable>{{ $summary['payable_formatted'] }}</strong>
    </p>
    <x-webx-form.submit :form="$summary['form']" class="webx-orders-checkout__sticky-place">{{ __('webx-orders::checkout.summary.place') }}</x-webx-form.submit>
</div>
