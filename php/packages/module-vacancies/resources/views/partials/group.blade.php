{{--
    One group of the index: a category and its open vacancies, or the last group of the ones in no
    category. `$group` is one element of `vacancies()->groups()`.
--}}
<section class="wx-vacancies__group">
    <h2>{{ $group['title'] }}</h2>
    <ul class="wx-vacancies__list">
        @foreach ($group['vacancies'] as $card)
            @include('webx-vacancies::partials.card', ['card' => $card])
        @endforeach
    </ul>
</section>
