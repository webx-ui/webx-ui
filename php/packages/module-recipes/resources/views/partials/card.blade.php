{{--
    One recipe in the catalogue: `$card` is what `recipes()` gives a template (see Rendering\Cards).

    The standard look of the `recipe-card` component: a site that presses "Customise" in the panel
    gets this file as the draft of its own card, and until it publishes one, this is what prints.

    The category and the services are links of their own, so they sit outside the card's link —
    a link inside a link is not a link at all.
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
    @if (($card['service_links'] ?? []) !== [])
        <span class="wx-recipes__services">
            @foreach ($card['service_links'] as $service)
                <a href="{{ $service['url'] }}">{{ $service['title'] }}</a>@if (! $loop->last), @endif
            @endforeach
        </span>
    @endif
    @if (($card['category_links'] ?? []) !== [] || $card['minutes'])
        <span class="wx-recipes__meta">
            @if (($card['category_links'] ?? []) !== [])
                <a class="wx-recipes__category" href="{{ $card['category_links'][0]['url'] }}">{{ $card['category_links'][0]['title'] }}</a>
            @endif
            @if ($card['minutes'])
                <span class="wx-recipes__time">{{ WebxUi\Recipes\Rendering\Duration::format($card['minutes']) }}</span>
            @endif
        </span>
    @endif
</li>
