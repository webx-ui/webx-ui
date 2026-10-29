{{--
    The section of a slot that failed to answer (spec §22.1): the page lives on, the error is in the
    log, and the confirmation does not go through until the slot answers again.

    Classes: webx-orders-checkout__failed.
--}}
<p class="webx-orders-checkout__failed" role="alert">{{ __('webx-orders::checkout.steps.failed') }}</p>
