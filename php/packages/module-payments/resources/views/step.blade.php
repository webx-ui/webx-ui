{{--
    The payment step (spec §10.1): what the checkout puts into its slot «payment», and what the
    API's redraw answers. Without JavaScript it is one form to `PUT /api/payments/selection` that
    comes back to the checkout: the methods as radios and «Save». With `step.js` a choice is
    written at once and the checkout hears of it (`$event`). One method is one line, chosen.

    $step — `WebxUi\Payments\Checkout\StepState`; $urls['selection'] — where the choice goes;
    $event — what the owner wants dispatched on `document` after a change; $script — the address of
    the script, null when the page has it already; $errors — the session's, a refusal under `payment`.

    Override: <layer>/views/vendor/webx-payments/step.blade.php.
    Classes: see AGENTS.md; states on the root — is-live (the script runs: «Save» steps aside),
    is-loading (a write is on its way); on a method — is-chosen; on the list — is-single.
--}}
@php($single = count($step->options) === 1 ? $step->options[0] : null)
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
            <fieldset @class(['webx-payments__methods', 'is-single' => $single !== null])>
                <legend class="webx-payments__methods-title">{{ __('webx-payments::buyer.step.methods') }}</legend>
                @foreach ($step->options as $option)
                    @php($chosen = $step->chosen?->key() === $option->key() || $single !== null)
                    <label @class(['webx-payments__method', 'is-chosen' => $chosen])>
                        <input class="webx-payments__radio" type="radio" name="method" value="{{ $option->key() }}" @checked($chosen) @if ($single !== null && $step->chosen === null) data-autosave @endif>
                        @if ($option->icon !== null && $option->icon !== '')
                            <img class="webx-payments__icon" src="{{ $option->icon }}" alt="" width="32" height="32" loading="lazy">
                        @endif
                        <span class="webx-payments__method-text">
                            <span class="webx-payments__method-title">{{ $option->title }}</span>
                            @if ($option->note !== null)
                                <span class="webx-payments__method-note">{{ $option->note }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </fieldset>

            <button type="submit" class="webx-payments__save webx-payments__nojs">{{ __('webx-payments::buyer.step.save') }}</button>
        @endif
    </form>

    @if (($script ?? null) !== null)
        <script type="module" src="{{ $script }}"></script>
    @endif
</section>
