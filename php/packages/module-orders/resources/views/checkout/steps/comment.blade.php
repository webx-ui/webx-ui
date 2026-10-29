{{--
    The step «Comment» (spec §20.2): one optional field; gone with `orders.checkout.comment = off`.

    Classes: webx-orders-checkout__form.
--}}
<x-webx-form :action="$action" id="checkout-step-comment-form" class="webx-orders-checkout__form">
    <x-webx-field type="textarea" name="comment" :label="__('webx-orders::checkout.fields.comment')" :value="$comment" :maxlength="$max" :rows="3" optional />
    <x-webx-form.submit>{{ __('webx-orders::checkout.steps.save') }}</x-webx-form.submit>
</x-webx-form>
