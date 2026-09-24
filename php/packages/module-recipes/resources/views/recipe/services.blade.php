{{-- The visible services this recipe is related to — cards as `services()` gives them. --}}
@if ($services !== [])
    <section class="wx-recipe__services">
        <h2>{{ trans('webx-recipes::site.services') }}</h2>
        <ul>
            @foreach ($services as $service)
                <li><a href="{{ $service['url'] }}">{{ $service['title'] }}</a></li>
            @endforeach
        </ul>
    </section>
@endif
