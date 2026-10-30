{{--
    The steps in their order (spec §20.1): each prints its form and its one-line summary with
    «Change». `is-done` — filled and ready, `is-open` — the first one still to fill, where the buyer
    goes next. Which of the form and the summary shows is `data-steps` of the page. A step of
    another module (§22) is marked `data-slot`.

    `data-slot-event` — what a slot dispatches on `document` after a change of the buyer's choice
    (§22.1); `data-summary` — where the fresh column of the totals is (§23.2): `checkout.js` redraws
    the column, and the payment step after delivery, by them. On the list, not on the root of the
    page: the attributes of a node of the composition are literals.

    Classes: webx-orders-checkout__steps, __step, __step-head, __step-summary, __step-edit,
    __step-body; states is-done, is-open.
--}}
<ol class="webx-orders-checkout__steps" data-slot-event="{{ \WebxUi\Orders\Checkout\Slots\SlotStep::CHANGED }}" data-summary="{{ $summaryUrl }}">
    @foreach ($steps as $step)
        <li id="checkout-step-{{ $step['key'] }}" @class(['webx-orders-checkout__step', 'is-done' => $step['done'], 'is-open' => $step['open']]) data-step="{{ $step['key'] }}" @if ($step['slot']) data-slot @endif>
            <h2 class="webx-orders-checkout__step-head">{{ $step['number'] }}. {{ $step['title'] }}</h2>
            @if ($step['summary'] !== null)
                <p class="webx-orders-checkout__step-summary">
                    {{ $step['summary'] }}
                    <a class="webx-orders-checkout__step-edit" href="#checkout-step-{{ $step['key'] }}-form">{{ __('webx-orders::checkout.steps.edit') }}</a>
                </p>
            @endif
            <div class="webx-orders-checkout__step-body">
                {!! $step['html'] !!}
            </div>
        </li>
    @endforeach
</ol>
