{{--
    The badges of one card. A tone is a class, never a colour: the site decides what «sale» looks
    like, and a site that wants its own badges publishes this view (`webx-catalog-labels-views`).
    A label in a colour of its own is `--custom`, its two colours variables on the element that the
    stylesheet reads — the model let in nothing but `#rrggbb`, and the attribute is escaped anyway.
--}}
@php($productBadges = isset($product) ? ($badges[$product->id] ?? []) : [])
@if ($productBadges !== [])
    <ul class="wx-catalog-badges">
        @foreach ($productBadges as $badge)
            @if (($badge['own'] ?? null) !== null)
                <li class="wx-catalog-badge wx-catalog-badge--custom" style="--webx-label-bg: {{ $badge['own']['background'] }}; --webx-label-fg: {{ $badge['own']['text'] }}" data-label="{{ $badge['code'] }}">{{ $badge['name'] }}</li>
            @else
                <li class="wx-catalog-badge wx-catalog-badge--{{ $badge['color'] }}" data-label="{{ $badge['code'] }}">{{ $badge['name'] }}</li>
            @endif
        @endforeach
    </ul>
@endif
