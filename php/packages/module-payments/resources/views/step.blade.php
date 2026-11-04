{{--
    The payment step (spec §10.1): what the checkout puts into its slot «payment», and what the
    API's redraw answers. Without JavaScript it is one form to `PUT /api/payments/selection` that
    comes back to the checkout: the list of methods (`<x-webx-payments::methods>`) and «Save». With
    `step.js` a choice is written at once and the checkout hears of it (`$event`).

    $step — `WebxUi\Payments\Checkout\StepState`; $groups — its options as `Methods::grouped()` reads
    them; $urls['selection'] — where the choice goes;
    $event — what the owner wants dispatched on `document` after a change; $script — the address of
    the script, null when the page has it already; $errors — the session's, a refusal under `payment`.

    Override: <layer>/views/vendor/webx-payments/step.blade.php.
    Classes: see AGENTS.md; states on the root — is-live (the script runs: «Save» steps aside),
    is-loading (a write is on its way).
--}}
<section id="webx-payments-step" class="webx-payments" data-webx-payments data-selection="{{ $urls['selection'] }}" @if (($event ?? null) !== null) data-checkout-event="{{ $event }}" @endif>
    <form class="webx-payments__form" method="post" action="{{ $urls['selection'] }}">
        @csrf
        @method('PUT')

        @if ($step->waiting)
            <p class="webx-payments__hint">{{ __('webx-payments::buyer.step.delivery_first') }}</p>
        @endif

        @if ($step->dropped)
            <p class="webx-payments__notice" role="status">{{ __('webx-payments::buyer.step.method_dropped') }}</p>
        @endif

        @if ($errors?->has('payment'))
            <p class="webx-payments__error" role="alert">{{ $errors->first('payment') }}</p>
        @endif

        @if ($step->options !== [])
            <x-webx-payments::methods :groups="$groups" :chosen="$step->chosen?->key()" :legend="__('webx-payments::buyer.step.methods')" />

            <button type="submit" class="webx-payments__save webx-payments__nojs">{{ __('webx-payments::buyer.step.save') }}</button>
        @endif
    </form>

    @if (($script ?? null) !== null)
        <script type="module" src="{{ $script }}"></script>
    @endif

    {{-- Here too, not only in the list: a step waiting for the delivery has no list yet, and the
         redraw that brings it inserts scripts the browser never runs. --}}
    @php($methods = app(\WebxUi\Payments\Rendering\Assets::class)->methods())
    @if ($methods !== null)
        <script type="module" src="{{ $methods }}"></script>
    @endif
</section>
