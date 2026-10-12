@if ($similar !== [])
    @include('webx-recipes::partials.look')
    <section class="wx-recipe__similar">
        <h2 class="wx-recipe__section-title">{{ trans('webx-recipes::site.similar') }}</h2>
        <ul class="wx-recipes__grid">
            @foreach ($similar as $card)
                @webxPart('recipe-card', ['card' => $card], 'webx-recipes::partials.card')
            @endforeach
        </ul>
    </section>
@endif
