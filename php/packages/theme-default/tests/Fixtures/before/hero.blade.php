@php
    $first = \WebxUi\Admin\Links\LinkAttributes::of($button_link, $button_url);
    $second = \WebxUi\Admin\Links\LinkAttributes::of($second_link, $second_url);
    $look = in_array($tone, ['surface', 'accent', 'image'], true) ? $tone : 'plain';
    $picture = $look === 'image' && is_array($image) && ! empty($image['url']) ? $image : null;
    $framed = $look === 'image' && \WebxUi\Widgets\Overlap::on($overlap ?? false);
@endphp
@if ($title || $subtitle)
<section @class(['b-hero', 'b-hero--'.$look, 'b-hero--center' => $align === 'center', 'site-bleed' => $width === 'bleed']) data-wx-block="hero"@webxOverlap($overlap ?? false)>
    @webxOverlapEdge($overlap ?? false, $overlap_edge ?? null, $overlap_edge_narrow ?? null)
    @if ($framed)
    <div class="b-hero__media">
    @endif
    @if ($picture)
        <img class="b-hero__picture"@if ($parallax) data-webx-parallax{!! in_array($parallax_strength ?? null, ['soft', 'strong'], true) ? '="'.$parallax_strength.'"' : '' !!} @endif src="{{ $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}" @if (! empty($picture['width'])) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif>
    @endif
    @if ($look === 'image')
        <div class="b-hero__shade"></div>
    @endif
    @if ($framed)
    </div>
    @endif
    <div class="b-hero__inner" data-webx-overlap-fade @webxReveal('', $no_reveal)>
        @if ($title)
            <h1 class="b-hero__title">@webxAccent($title)</h1>
        @endif
        @if ($subtitle)
            <p class="b-hero__subtitle">{{ $subtitle }}</p>
        @endif
        @if (($button_label && $first) || ($second_label && $second))
            <div class="b-hero__actions">
                @if ($button_label && $first)
                    <a class="b-hero__button" {{ $first }}>{{ $button_label }}</a>
                @endif
                @if ($second_label && $second)
                    <a class="b-hero__button b-hero__button--quiet" {{ $second }}>{{ $second_label }}</a>
                @endif
            </div>
        @endif
    </div>
</section>
@endif
