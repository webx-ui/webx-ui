{{--
    One group of the index: a category and its open vacancies, or the last group of the ones in no
    category. `$group` is one element of `vacancies()->groups()`. Each card goes through the point
    `vacancies.card`, so a theme's `parts/vacancies/card.blade.php` replaces it without the list.
--}}
<section class="wx-vacancies__group">
    <h2 class="wx-vacancies__group-title">{{ $group['title'] }}</h2>
    <ul class="wx-vacancies__list">
        @foreach ($group['vacancies'] as $card)
            @webxPart('vacancies.card', ['card' => $card], 'webx-vacancies::partials.card')
        @endforeach
    </ul>
</section>
