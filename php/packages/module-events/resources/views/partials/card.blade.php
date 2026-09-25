{{--
    One event in a list: `$card` is what `events()` gives a template (see Rendering\Cards).

    The category is a link of its own, so it sits outside the card's link — a link inside a link
    is not a link at all.
--}}
<li class="wx-events__card">
    <a class="wx-events__link" href="{{ $card['url'] }}">
        @if ($card['cover'] && $card['cover']['url'])
            <img class="wx-events__cover" src="{{ $card['cover']['url'] }}" alt="{{ $card['cover']['alt'] ?? '' }}"
                 @if ($card['cover']['width']) width="{{ $card['cover']['width'] }}" height="{{ $card['cover']['height'] }}" @endif
                 loading="lazy">
        @endif
        <span class="wx-events__name">{{ $card['title'] }}</span>
    </a>
    <span class="wx-events__meta">
        @if ($card['when'] !== '')
            <span class="wx-events__when">{{ $card['when'] }}</span>
        @endif
        @if ($card['attendance'] === 'online')
            <span class="wx-events__venue">{{ trans('webx-events::site.online') }}</span>
        @elseif ($card['venue'] !== '')
            <span class="wx-events__venue">{{ $card['venue'] }}</span>
        @endif
        @if (($card['category_links'] ?? []) !== [])
            <a class="wx-events__category" href="{{ $card['category_links'][0]['url'] }}">{{ $card['category_links'][0]['title'] }}</a>
        @endif
    </span>
</li>
