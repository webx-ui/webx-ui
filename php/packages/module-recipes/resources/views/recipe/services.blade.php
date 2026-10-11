{{-- The visible services this recipe is related to — cards as `services()` gives them. --}}
@if ($services !== [])
    <section class="wx-recipe__services">
        <h2 class="wx-recipe__section-title">{{ trans('webx-recipes::site.services') }}</h2>
        <ul class="wx-recipe__service-list">
            @foreach ($services as $service)
                <li class="wx-recipe__service"><a class="wx-recipe__service-link" href="{{ $service['url'] }}">{{ $service['title'] }}</a></li>
            @endforeach
        </ul>
    </section>
@endif
