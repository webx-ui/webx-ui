{{--
    The badges of one card. The tone is a class, never a colour: the site decides what «sale» looks
    like, and a site that wants its own badges publishes this view (`webx-catalog-labels-views`).
--}}
@php($productBadges = isset($product) ? ($badges[$product->id] ?? []) : [])
@if ($productBadges !== [])
    <ul class="wx-catalog-badges">
        @foreach ($productBadges as $badge)
            <li class="wx-catalog-badge wx-catalog-badge--{{ $badge['color'] }}" data-label="{{ $badge['code'] }}">{{ $badge['name'] }}</li>
        @endforeach
    </ul>
@endif
