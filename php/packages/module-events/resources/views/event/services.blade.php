{{-- The visible services this event is related to — cards as `services()` gives them. --}}
@if ($services !== [])
    <section class="wx-event__services">
        <h2 class="wx-event__section-title">{{ trans('webx-events::site.services') }}</h2>
        <ul class="wx-event__service-list">
            @foreach ($services as $service)
                <li class="wx-event__service"><a class="wx-event__service-link" href="{{ $service['url'] }}">{{ $service['title'] }}</a></li>
            @endforeach
        </ul>
    </section>
@endif
