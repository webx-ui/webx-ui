{{--
    One list of links to landings, anchored by their names; nothing at all when it is empty.
--}}
@if ($links !== [])
    <nav class="webx-catalog-landings-links webx-catalog-landings-links--{{ $modifier }}">
        <h2 class="webx-catalog-landings-links__title">{{ $title }}</h2>
        <ul class="webx-catalog-landings-links__list">
            @foreach ($links as $link)
                <li><a href="{{ $link['url'] }}">{{ $link['name'] }}</a></li>
            @endforeach
        </ul>
    </nav>
@endif
