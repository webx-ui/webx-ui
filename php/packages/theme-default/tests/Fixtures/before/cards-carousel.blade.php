@php
    // A card's photos: the picture, then the more photos — the ones deleted from the library left out.
    $photosOf = static fn (array $card): array => array_values(array_filter([$card['image'] ?? null, ...(is_array($card['photos'] ?? null) ? $card['photos'] : [])], static fn ($photo): bool => is_array($photo) && ! empty($photo['url'])));
    $shown = array_values(array_filter(is_array($cards) ? $cards : [], static fn ($item): bool => is_array($item) && (trim((string) ($item['title'] ?? '')) !== '' || trim((string) ($item['text'] ?? '')) !== '' || $photosOf($item) !== [])));
    $across = in_array((string) $columns, ['2', '4'], true) ? (string) $columns : '3';
    // A carousel of cards, or the grid every block saved before the field is.
    $carousel = ($view ?? 'grid') === 'slider';
    // As many across as the grid has at the same width of the column, and no more than it was told.
    $views = array_slice([0 => 1, 512 => 2, 832 => 3, 1024 => 4], 0, (int) $across, true);
    $turn = ! empty($autoplay) ? max(2, min(30, (int) ($interval ?? 5) ?: 5)) * 1000 : false;
    $label = $heading ? trim(strip_tags(\WebxUi\Themes\ThemeAccent::plain($heading))) : null;
    $drawn = [];
@endphp
@foreach ($shown as $card)
    @php
        $photos = $photosOf($card);
        $picture = $photos[0] ?? null;
        // Several photos turn in a photo card; an icon is one picture.
        $turning = $pictures !== 'icon' && count($photos) > 1;
        $title = trim((string) ($card['title'] ?? ''));
        $more = trim((string) ($card['link_label'] ?? ''));
        $href = \WebxUi\Admin\Links\LinkAttributes::of($card['link'] ?? null, $card['link_url'] ?? null);
        $chips = array_values(array_filter(array_map(static fn ($chip): string => is_array($chip) ? trim((string) ($chip['label'] ?? '')) : '', is_array($card['chips'] ?? null) ? $card['chips'] : []), static fn (string $chip): bool => $chip !== ''));
        ob_start();
    @endphp
                    @if ($turning)
                        <x-webx-photos class="b-cards__photos" :images="$photos" ratio="3/2" :label="$title !== '' ? $title : null" />
                    @elseif ($picture)
                        <img class="b-cards__picture" src="{{ $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}" loading="lazy" @if (! empty($picture['width'])) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif>
                    @endif
                    <div class="b-cards__body">
                        @if ($title !== '' || ($href !== null && $more !== ''))
                            <h3 class="b-cards__title">
                                @if ($href !== null)
                                    <a class="b-cards__link" {{ $href }}>{{ $title !== '' ? $title : $more }}</a>
                                @else
                                    {{ $title }}
                                @endif
                            </h3>
                        @endif
                        @if ($chips)
                            <ul class="b-cards__chips" role="list">
                                @foreach ($chips as $chip)
                                    <li class="b-cards__chip">{{ $chip }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (trim((string) ($card['text'] ?? '')) !== '')
                            <p class="b-cards__text">{{ $card['text'] }}</p>
                        @endif
                        @if ($href !== null && $more !== '' && $title !== '')
                            <span class="b-cards__more" aria-hidden="true">{{ $more }}<x-theme::icon name="arrow-right" class="b-cards__arrow" /></span>
                        @endif
                    </div>
    @php
        // The card is drawn once and stands in the grid's cell or in a slide: one markup for both.
        $drawn[] = ['linked' => $href !== null, 'surface' => $turning && $href !== null, 'html' => new \Illuminate\Support\HtmlString((string) ob_get_clean())];
    @endphp
@endforeach
@if ($shown || ($eyebrow ?? null) || $heading)
<section class="b-cards b-cards--{{ $across }} b-cards--{{ $pictures === 'icon' ? 'icon' : 'photo' }}{{ $carousel && $shown ? ' b-cards--carousel' : '' }}" data-wx-block="cards"@webxOverlap($overlap ?? false)>
    @webxOverlapEdge($overlap ?? false, $overlap_edge ?? null, $overlap_edge_narrow ?? null)
    @if (($eyebrow ?? null) || $heading || $intro)
        <div class="b-cards__head" @webxReveal('', $no_reveal)>
            @if ($eyebrow ?? null)
                <p class="b-cards__eyebrow">{{ $eyebrow }}</p>
            @endif
            @if ($heading)
                <h2 class="b-cards__heading">@webxAccent($heading)</h2>
            @endif
            @if ($intro)
                <p class="b-cards__intro">{{ $intro }}</p>
            @endif
        </div>
    @endif
    @if ($shown && $carousel)
        <div class="b-cards__carousel" @webxReveal('', $no_reveal)>
            <x-webx-slider variant="cards" :label="$label ?: null" :per-view="$views" :arrows="(bool) ($arrows ?? true)" :pagination="(bool) ($dots ?? true)" :loop="(bool) ($endless ?? true)" :autoplay="$turn">
                @foreach ($drawn as $card)
                    <x-webx-slide>
                        <div @class(['b-cards__card', 'is-linked' => $card['linked']]) @if ($card['surface']) data-webx-photos-surface @endif>{{ $card['html'] }}</div>
                    </x-webx-slide>
                @endforeach
            </x-webx-slider>
        </div>
    @elseif ($shown)
        <ul class="b-cards__grid" role="list" @webxReveal('stagger', $no_reveal)>
            @foreach ($drawn as $card)
                <li @class(['b-cards__card', 'is-linked' => $card['linked']]) @if ($card['surface']) data-webx-photos-surface @endif>
{{ $card['html'] }}                </li>
            @endforeach
        </ul>
    @endif
</section>
@endif
