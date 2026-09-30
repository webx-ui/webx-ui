{{--
    The properties marked «in the card», in the order of the set: «Diagonal: 15.6″». A site that
    wants its own list publishes this view (`webx-catalog-properties-views`).
--}}
@php($cardProperties = isset($product, $productProperties) ? $productProperties->card($product) : [])
@if ($cardProperties !== [])
    <ul class="wx-catalog-properties">
        @foreach ($cardProperties as $shown)
            <li class="wx-catalog-properties__item" data-property="{{ $shown->code }}">
                <span class="wx-catalog-properties__label">{{ $shown->label }}:</span>
                <span class="wx-catalog-properties__value">{{ $shown->formatted }}</span>
            </li>
        @endforeach
    </ul>
@endif
