{{--
    The line «Delivery cost» (§12.3, §9.3): the amount, «Free» with the old price crossed out, or
    «By carrier rates» with an estimate — not in the total then. `$cost` is
    `WebxUi\Delivery\DeliveryCost`.

    Classes: webx-delivery__summary, __summary-label, __summary-value, __price-was,
    __price-estimate.
--}}
@props(['cost'])
<p class="webx-delivery__summary" data-delivery-cost="{{ $cost->quote->kind->value }}">
    <span class="webx-delivery__summary-label">{{ $cost->label }}</span>
    <span class="webx-delivery__summary-value">
        @if ($cost->wasFormatted !== null)
            <s class="webx-delivery__price-was">{{ $cost->wasFormatted }}</s>
        @endif
        {{ $cost->text }}
        @if ($cost->estimateFormatted !== null && ! $cost->inTotal())
            <span class="webx-delivery__price-estimate">≈ {{ $cost->estimateFormatted }}</span>
        @endif
    </span>
</p>
