{{--
    One recipe in the catalogue: `$card` is what `recipes()` gives a template (see Rendering\Cards).
--}}
<li class="wx-recipes__card">
    <a class="wx-recipes__link" href="{{ $card['url'] }}">
        @if ($card['cover'] && $card['cover']['url'])
            <img class="wx-recipes__cover" src="{{ $card['cover']['url'] }}" alt="{{ $card['cover']['alt'] ?? '' }}"
                 @if ($card['cover']['width']) width="{{ $card['cover']['width'] }}" height="{{ $card['cover']['height'] }}" @endif
                 loading="lazy">
        @endif
        <span class="wx-recipes__name">{{ $card['title'] }}</span>
    </a>
    @if ($card['minutes'])
        <span class="wx-recipes__time">{{ WebxUi\Recipes\Rendering\Duration::format($card['minutes']) }}</span>
    @endif
</li>
