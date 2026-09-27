{{-- The visible services this event is related to — cards as `services()` gives them. --}}
@if ($services !== [])
    <section class="wx-event__services">
        <h2>{{ trans('webx-events::site.services') }}</h2>
        <ul>
            @foreach ($services as $service)
                <li><a href="{{ $service['url'] }}">{{ $service['title'] }}</a></li>
            @endforeach
        </ul>
    </section>
@endif
