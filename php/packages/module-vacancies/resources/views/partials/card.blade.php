{{--
    One vacancy in a list: `$card` is what `vacancies()` gives a template (see Rendering\Cards).
--}}
<li class="wx-vacancies__card">
    <a class="wx-vacancies__link" href="{{ $card['url'] }}">{{ $card['title'] }}</a>
    <span class="wx-vacancies__meta">
        @if ($card['workplace'] === 'remote')
            <span>{{ trans('webx-vacancies::vacancy.workplace.remote') }}</span>
        @elseif ($card['city'] !== '')
            <span>{{ $card['city'] }}</span>
        @endif
        @if ($card['employment'] !== [])
            <span>{{ implode(', ', $card['employment']) }}</span>
        @endif
        @if ($card['salary'] !== '')
            <span>{{ $card['salary'] }}</span>
        @endif
    </span>
    @if ($card['lead'] !== '')
        <p class="wx-vacancies__lead">{{ $card['lead'] }}</p>
    @endif
</li>
