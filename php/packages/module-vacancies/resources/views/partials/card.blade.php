{{--
    One vacancy in a list: `$card` is what `vacancies()` gives a template (see Rendering\Cards).

    A group prints it through the point `vacancies.card` (THEMES §10.4): a theme that wants another
    card writes `views/parts/vacancies/card.blade.php` — keeping the `<li>`, because the list
    around it is still this module's.
--}}
<li class="wx-vacancies__card">
    <a class="wx-vacancies__link" href="{{ $card['url'] }}">{{ $card['title'] }}</a>
    <span class="wx-vacancies__meta">
        @if ($card['workplace'] === 'remote')
            <span class="wx-vacancies__place">{{ trans('webx-vacancies::vacancy.workplace.remote') }}</span>
        @elseif ($card['city'] !== '')
            <span class="wx-vacancies__place">{{ $card['city'] }}</span>
        @endif
        @if ($card['employment'] !== [])
            <span class="wx-vacancies__employment">{{ implode(', ', $card['employment']) }}</span>
        @endif
        @if ($card['salary'] !== '')
            <span class="wx-vacancies__salary">{{ $card['salary'] }}</span>
        @endif
    </span>
    @if ($card['lead'] !== '')
        <p class="wx-vacancies__lead">{{ $card['lead'] }}</p>
    @endif
</li>
