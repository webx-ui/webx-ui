@if ($similar !== [])
    <section class="wx-recipe__similar">
        <h2>{{ trans('webx-recipes::site.similar') }}</h2>
        <ul class="wx-recipes__grid">
            @foreach ($similar as $card)
                @include('webx-recipes::partials.card', ['card' => $card])
            @endforeach
        </ul>
    </section>
@endif
