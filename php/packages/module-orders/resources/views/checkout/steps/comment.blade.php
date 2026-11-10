{{--
    The block «Additional» (spec §20.2, WEBX_UI_ORDER_FIELDS.md §4.2), the step `comment`: the extra
    fields before the comment, the comment — gone with `orders.checkout.comment = off` — and the fields
    after it, one form. A theme that copies this view keeps the two lines of the places.

    Classes: webx-orders-checkout__form.
--}}
<x-webx-form :action="$action" id="checkout-step-comment-form" class="webx-orders-checkout__form">
    @include('webx-orders::checkout.parts.fields', ['place' => 'extra-before-comment'])
    @if ($withComment)
        <x-webx-field type="textarea" name="comment" :label="__('webx-orders::checkout.fields.comment')" :value="$comment" :maxlength="$max" :rows="3" optional />
    @endif
    @include('webx-orders::checkout.parts.fields', ['place' => 'extra-after-comment'])
    <x-webx-form.submit>{{ __('webx-orders::checkout.steps.save') }}</x-webx-form.submit>
</x-webx-form>
