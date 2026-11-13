@php
    $button = \WebxUi\Admin\Links\LinkAttributes::of($button_link ?? null);
    $picture = is_array($image) && ! empty($image['url']) ? $image : null;
    $veil = ($shade ?? 'dark') === 'light' ? 'light' : 'dark';
    // A tile is an icon or a value with its unit, a label and a line; one with none of them is not printed.
    $shown = [];
    foreach (is_array($tiles) ? $tiles : [] as $tile) {
        if (! is_array($tile)) {
            continue;
        }
        $kind = ($tile['kind'] ?? 'icon') === 'value' ? 'value' : 'icon';
        $icon = $kind === 'icon' && is_array($tile['icon'] ?? null) && ! empty($tile['icon']['url']) ? $tile['icon'] : null;
        $value = $kind === 'value' ? trim((string) ($tile['value'] ?? '')) : '';
        $unit = $value !== '' ? trim((string) ($tile['unit'] ?? '')) : '';
        $label = trim((string) ($tile['label'] ?? ''));
        $line = trim((string) ($tile['note'] ?? ''));
        if ($icon || $value !== '' || $label !== '' || $line !== '') {
            // An SVG icon is painted in the colour of the words through a mask: one file reads on both shades.
            // The address stands inside url('…') of a style attribute: its quotes and brackets encoded.
            $mask = $icon && (strtolower((string) ($icon['extension'] ?? '')) === 'svg' || ($icon['mime'] ?? null) === 'image/svg+xml')
                ? str_replace(["'", '"', '(', ')', '\\'], ['%27', '%22', '%28', '%29', '%5C'], (string) $icon['url'])
                : null;
            $shown[] = compact('kind', 'icon', 'mask', 'value', 'unit', 'label', 'line');
        }
        if (count($shown) === 4) {
            break;
        }
    }
    $framed = \WebxUi\Widgets\Overlap::on($overlap ?? false);
    $words = $eyebrow || $heading || $text || ($button_label && $button);
@endphp
@if ($words || $shown)
<section @class(['b-text-tiles', 'b-text-tiles--'.$veil, 'b-text-tiles--'.count($shown), 'b-text-tiles--words' => $words, 'site-bleed' => ($width ?? 'bleed') !== 'column']) data-wx-block="text-tiles"@webxOverlap($overlap ?? false)>
    @webxOverlapEdge($overlap ?? false, $overlap_edge ?? null, $overlap_edge_narrow ?? null)
    @if ($framed)
    <div class="b-text-tiles__media">
    @endif
    @if ($picture)
        <img class="b-text-tiles__picture"@if ($parallax) data-webx-parallax{!! in_array($parallax_strength ?? null, ['soft', 'strong'], true) ? '="'.$parallax_strength.'"' : '' !!} @endif src="{{ $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}" loading="lazy" @if (! empty($picture['width'])) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif>
    @endif
    <div class="b-text-tiles__shade"></div>
    @if ($framed)
    </div>
    @endif
    <div class="b-text-tiles__inner" data-webx-overlap-fade>
        @if ($words)
            <div class="b-text-tiles__words" @webxReveal('', $no_reveal)>
                @if ($eyebrow)
                    <p class="b-text-tiles__eyebrow">{{ $eyebrow }}</p>
                @endif
                @if ($heading)
                    <h2 class="b-text-tiles__heading">@webxAccent($heading)</h2>
                @endif
                @if ($text)
                    <p class="b-text-tiles__text">{{ $text }}</p>
                @endif
                @if ($button_label && $button)
                    <a class="b-text-tiles__button" {{ $button }}>{{ $button_label }}</a>
                @endif
            </div>
        @endif
        @if ($shown)
            <ul class="b-text-tiles__tiles" role="list" @webxReveal('stagger', $no_reveal)>
                @foreach ($shown as $tile)
                    <li class="b-text-tiles__tile b-text-tiles__tile--{{ $tile['kind'] }}">
                        @if ($tile['mask'])
                            <span class="b-text-tiles__icon b-text-tiles__icon--mask" style="--b-text-tiles-icon: url('{{ $tile['mask'] }}')" @if (! empty($tile['icon']['alt'])) role="img" aria-label="{{ $tile['icon']['alt'] }}" @else aria-hidden="true" @endif></span>
                        @elseif ($tile['icon'])
                            <img class="b-text-tiles__icon" src="{{ $tile['icon']['url'] }}" alt="{{ $tile['icon']['alt'] ?? '' }}" loading="lazy" @if (! empty($tile['icon']['width'])) width="{{ $tile['icon']['width'] }}" height="{{ $tile['icon']['height'] }}" @endif>
                        @endif
                        @if ($tile['value'] !== '')
                            <p class="b-text-tiles__value">{{ $tile['value'] }}@if ($tile['unit'] !== '') <span class="b-text-tiles__unit">{{ $tile['unit'] }}</span>@endif</p>
                        @endif
                        @if ($tile['label'] !== '')
                            <p class="b-text-tiles__label">{{ $tile['label'] }}</p>
                        @endif
                        @if ($tile['line'] !== '')
                            <p class="b-text-tiles__line">{{ $tile['line'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
@endif
