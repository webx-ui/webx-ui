{{--
    One service in a listing: `$service` is a `WebxUi\Services\Models\Service`.

    The index and a category print it through the point `services.card` (THEMES §10.4): a theme
    that wants another card writes `views/parts/services/card.blade.php` — keeping the `<li>`,
    because the list around it is still this module's — and both listings follow.
--}}
<li class="webx-services-card">
    <a class="webx-services-card__link" href="{{ $service->url() }}">
        @if ($url = $service->coverUrl())
            <img class="webx-services-card__cover" src="{{ $url }}" alt="{{ $service->title }}" loading="lazy">
        @endif
        <h3 class="webx-services-card__title">{{ $service->title }}</h3>
    </a>
    @if ($service->lead)
        <p class="webx-services-card__lead">{{ $service->lead }}</p>
    @endif
</li>
