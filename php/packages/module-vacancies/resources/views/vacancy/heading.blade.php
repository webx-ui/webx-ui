<header class="wx-vacancy__heading">
    <h1>{{ $title }}</h1>
    {{-- Still a page when it is closed (decision 4); expired or closed by hand, the reader is told
         the same thing. --}}
    @if ($closed)
        <p class="wx-vacancy__closed"><strong>{{ trans('webx-vacancies::vacancy.closed') }}</strong></p>
    @endif
    @if ($lead !== '')
        <p class="wx-vacancy__lead">{{ $lead }}</p>
    @endif
</header>
