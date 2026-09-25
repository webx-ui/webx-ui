<header class="wx-event__heading">
    <h1>{{ $title }}</h1>
    {{-- Still a page when it is over (decision 6): the report of it, with no way to book. --}}
    @if ($past)
        <p class="wx-event__past"><strong>{{ trans('webx-events::site.past') }}</strong></p>
    @endif
    @if ($lead !== '')
        <p class="wx-event__lead">{{ $lead }}</p>
    @endif
</header>
