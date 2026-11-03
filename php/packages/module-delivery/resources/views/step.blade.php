{{--
    The delivery step (spec §12.3): what the checkout puts into its slot «delivery», and what the
    API's redraw answers. Without JavaScript it is one form to `PUT /api/delivery/selection` that
    comes back here: the town typed with a list to start from, «Show delivery methods», the
    methods as radios and under the chosen one its point or its address. With `delivery.js` every
    change is written at once and the step is drawn again from the answer; the town is chosen in
    the dialog «Where to deliver the order?» (decision 40).

    `$step` — `WebxUi\Delivery\Checkout\StepView::build()`; `$event` — what the owner wants
    dispatched on `document` after a change (the checkout's `webx:checkout:slot-changed`).

    Override: <layer>/views/vendor/webx-delivery/step.blade.php, or one component of it under
    components/. Classes: see AGENTS.md; the script's states on the root — is-live (it runs: the
    fields for a page without it step aside), is-loading (a write is on its way).
--}}
<section id="{{ $step['id'] }}" class="webx-delivery" data-webx-delivery data-selection="{{ $step['urls']['selection'] }}" @if (($event ?? null) !== null) data-checkout-event="{{ $event }}" @endif @if ($step['name'] !== null) data-name="{{ $step['name'] }}" @endif>
    <form class="webx-delivery__form" method="post" action="{{ $step['urls']['selection'] }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="{{ ($step['field'])('_form') }}" value="step">

        <x-webx-delivery::country-picker :step="$step" />
        <x-webx-delivery::locality-picker :step="$step" />

        @if ($step['locality'] !== null)
            <x-webx-delivery::methods :step="$step" />
        @endif

        @if ($step['cost'] !== null)
            <x-webx-delivery::summary-line :cost="$step['cost']" />
        @endif

        @if ($step['methods'] !== [])
            <button type="submit" class="webx-delivery__save webx-delivery__nojs">{{ __('webx-delivery::delivery.step.save') }}</button>
        @endif
    </form>

    <x-webx-delivery::locality-picker :step="$step" dialog />

    @if (($script = app(\WebxUi\Delivery\Rendering\Assets::class)->script()) !== null)
        <script type="module" src="{{ $script }}"></script>
    @endif
</section>
