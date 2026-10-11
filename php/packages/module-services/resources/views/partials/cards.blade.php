{{--
    The cards of a listing, used by the index and by a category page.

    Each card goes through `@webxPart`, so a theme's `parts/services/card.blade.php` replaces the
    card without replacing the listing.
--}}
<ul class="webx-services-cards">
    @foreach ($services as $service)
        @webxPart('services.card', ['service' => $service], 'webx-services::partials.card')
    @endforeach
</ul>
