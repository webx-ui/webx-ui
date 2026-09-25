@if ($similar !== [])
    <section class="wx-recipe__similar">
        <h2>{{ trans('webx-recipes::site.similar') }}</h2>
        <ul class="wx-recipes__grid">
            @foreach ($similar as $card)
                @webxPart('recipe-card', ['card' => $card], 'webx-recipes::partials.card')
            @endforeach
        </ul>
    </section>
@endif
