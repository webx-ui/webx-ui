{{--
    One card per service, used by the index and by a category page.
--}}
<ul>
    @foreach ($services as $service)
        <li>
            <a href="{{ $service->url() }}">
                @if ($url = $service->coverUrl())
                    <img src="{{ $url }}" alt="{{ $service->title }}" loading="lazy">
                @endif
                <h3>{{ $service->title }}</h3>
            </a>
            @if ($service->lead)
                <p>{{ $service->lead }}</p>
            @endif
        </li>
    @endforeach
</ul>
