{{--
    The parts of the column a neighbour adds (spec §23.3, `CheckoutSummaryParts`): a promo code,
    a sum from a wallet. Each changes the cart through its own API and dispatches
    `webx:checkout:slot-changed`; the column is redrawn then. None — no markup.

    Classes: webx-orders-checkout__parts, __part.
--}}
@if ($parts !== [])
    <div class="webx-orders-checkout__parts">
        @foreach ($parts as $key => $html)
            <div class="webx-orders-checkout__part" data-part="{{ $key }}">{!! $html !!}</div>
        @endforeach
    </div>
@endif
