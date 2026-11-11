@php
    $first = \WebxUi\Admin\Links\LinkAttributes::of($button_link, $button_url);
    $second = \WebxUi\Admin\Links\LinkAttributes::of($second_link, $second_url);
    $look = in_array($tone, ['surface', 'plain'], true) ? $tone : 'accent';
@endphp
@if ($heading || ($button_label && $first))
<section class="b-cta b-cta--{{ $look }}" data-wx-block="cta"@webxOverlap($overlap ?? false)>
    @webxOverlapEdge($overlap ?? false, $overlap_edge ?? null, $overlap_edge_narrow ?? null)
    <div class="b-cta__inner" @webxReveal('', $no_reveal)>
        <div class="b-cta__words">
            @if ($heading)
                <h2 class="b-cta__heading">@webxAccent($heading)</h2>
            @endif
            @if ($text)
                <p class="b-cta__text">{{ $text }}</p>
            @endif
        </div>
        @if (($button_label && $first) || ($second_label && $second))
            <div class="b-cta__actions">
                @if ($button_label && $first)
                    <a class="b-cta__button" {{ $first }}>{{ $button_label }}</a>
                @endif
                @if ($second_label && $second)
                    <a class="b-cta__button b-cta__button--quiet" {{ $second }}>{{ $second_label }}</a>
                @endif
            </div>
        @endif
    </div>
</section>
@endif
