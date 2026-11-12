@php
    $first = \WebxUi\Admin\Links\LinkAttributes::of($button_link, $button_url);
    $second = \WebxUi\Admin\Links\LinkAttributes::of($second_link, $second_url);
    $look = in_array($tone, ['surface', 'plain', 'image'], true) ? $tone : 'accent';
    $picture = $look === 'image' && is_array($image ?? null) && ! empty($image['url']) ? $image : null;
    $buttons = ($button_label && $first) || ($second_label && $second);
    // A photo with nothing over it is the photo alone: no shade, no padding around it.
    $bare = $picture && ! ($eyebrow ?? null) && ! $heading && ! $text && ! $buttons;
    $veil = ($shade ?? 'dark') === 'light' ? 'light' : 'dark';
    $tall = $look === 'image' && in_array($height ?? null, ['screen', 'ratio'], true) ? $height : null;
    $ratio = $tall === 'ratio' && in_array($ratio ?? null, ['21-9', '16-9', '3-2', '4-3'], true) ? $ratio : '16-9';
@endphp
@if (($eyebrow ?? null) || $heading || ($button_label && $first) || $picture)
<section @class(['b-cta', 'b-cta--'.$look, 'b-cta--'.$veil => $look === 'image', 'b-cta--bare' => $bare, 'b-cta--'.$tall => $tall, 'b-cta--ratio-'.$ratio => $tall === 'ratio', 'site-bleed' => ($width ?? null) === 'bleed']) data-wx-block="cta"@webxOverlap($overlap ?? false)>
    @webxOverlapEdge($overlap ?? false, $overlap_edge ?? null, $overlap_edge_narrow ?? null)
    <div class="b-cta__inner" @webxReveal('', $no_reveal)>
        @if ($picture)
            <img class="b-cta__picture"@if ($parallax ?? false) data-webx-parallax{!! in_array($parallax_strength ?? null, ['soft', 'strong'], true) ? '="'.$parallax_strength.'"' : '' !!} @endif src="{{ $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}" loading="lazy" @if (! empty($picture['width'])) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif>
        @endif
        @if ($look === 'image' && ! $bare)
            <div class="b-cta__shade"></div>
        @endif
        @if (! $bare)
        <div class="b-cta__words">
            @if ($eyebrow ?? null)
                <p class="b-cta__eyebrow">{{ $eyebrow }}</p>
            @endif
            @if ($heading)
                <h2 class="b-cta__heading">@webxAccent($heading)</h2>
            @endif
            @if ($text)
                <p class="b-cta__text">{{ $text }}</p>
            @endif
        </div>
        @endif
        @if ($buttons)
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
