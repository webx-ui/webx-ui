@php
    $shown = array_values(array_filter(is_array($cards) ? $cards : [], static fn ($item): bool => is_array($item) && (trim((string) ($item['title'] ?? '')) !== '' || trim((string) ($item['text'] ?? '')) !== '' || ! empty($item['image']['url']))));
    $across = in_array((string) $columns, ['2', '4'], true) ? (string) $columns : '3';
@endphp
@if ($shown || $heading)
<section class="b-cards b-cards--{{ $across }} b-cards--{{ $pictures === 'icon' ? 'icon' : 'photo' }}" data-wx-block="cards">
    @if ($heading || $intro)
        <div class="b-cards__head" @webxReveal('', $no_reveal)>
            @if ($heading)
                <h2 class="b-cards__heading">@webxAccent($heading)</h2>
            @endif
            @if ($intro)
                <p class="b-cards__intro">{{ $intro }}</p>
            @endif
        </div>
    @endif
    @if ($shown)
        <ul class="b-cards__grid" role="list" @webxReveal('stagger', $no_reveal)>
            @foreach ($shown as $card)
                @php
                    $picture = is_array($card['image'] ?? null) && ! empty($card['image']['url']) ? $card['image'] : null;
                    $title = trim((string) ($card['title'] ?? ''));
                    $more = trim((string) ($card['link_label'] ?? ''));
                    $href = \WebxUi\Admin\Links\LinkAttributes::of($card['link'] ?? null, $card['link_url'] ?? null);
                @endphp
                <li @class(['b-cards__card', 'is-linked' => $href !== null])>
                    @if ($picture)
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
                        @if (trim((string) ($card['text'] ?? '')) !== '')
                            <p class="b-cards__text">{{ $card['text'] }}</p>
                        @endif
                        @if ($href !== null && $more !== '' && $title !== '')
                            <span class="b-cards__more" aria-hidden="true">{{ $more }}<x-theme::icon name="arrow-right" class="b-cards__arrow" /></span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endif
