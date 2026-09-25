{{--
    The catalogue — the index, a category page and the "Recipes" block all print this, so a site
    that publishes it changes all three at once. `$catalog` is a Rendering\Catalog: cards, the
    nutrient filter and the pages as ready links, or a showcase with a link to all recipes.

    Links, not buttons: without a script the filter and the pages still work, and a search engine
    reads the pages the way a reader clicks through them.
--}}
@once
    @push('head')
        <style>
            .wx-recipes { container-type: inline-size; }
            .wx-recipes__filter, .wx-recipes__pages { display: flex; flex-wrap: wrap; gap: 0.5em; margin: 0 0 1.25em; padding: 0; list-style: none; }
            .wx-recipes__chip { display: inline-block; padding: 0.3em 0.9em; border: 1px solid currentColor; border-radius: 999px; color: inherit; text-decoration: none; opacity: 0.65; }
            .wx-recipes__chip[aria-current] { opacity: 1; font-weight: 600; }
            .wx-recipes__grid { display: grid; grid-template-columns: repeat(var(--wx-recipes-columns, 3), minmax(0, 1fr)); gap: 1.5em; margin: 0 0 1.5em; padding: 0; list-style: none; }
            @container (max-width: 40rem) { .wx-recipes__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @container (max-width: 24rem) { .wx-recipes__grid { grid-template-columns: minmax(0, 1fr); } }
            .wx-recipes__link { display: flex; flex-direction: column; gap: 0.6em; color: inherit; text-decoration: none; }
            .wx-recipes__cover { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 0.5em; }
            .wx-recipes__name { font-weight: 600; }
            .wx-recipes__card { display: flex; flex-direction: column; gap: 0.4em; }
            .wx-recipes__services { font-size: 0.9em; }
            .wx-recipes__services a, .wx-recipes__category { color: inherit; }
            .wx-recipes__meta { display: flex; flex-wrap: wrap; gap: 0.5em; opacity: 0.7; font-size: 0.9em; }
            .wx-recipes__meta > * + *::before { content: "·"; margin-inline-end: 0.5em; }
            .wx-recipes__pages a[aria-current] { font-weight: 600; text-decoration: none; }
        </style>
    @endpush
@endonce

<div class="wx-recipes" style="--wx-recipes-columns: {{ $catalog->columns }}">
    @if ($catalog->nutrients !== [])
        <ul class="wx-recipes__filter" aria-label="{{ trans('webx-recipes::site.filter') }}">
            <li><a class="wx-recipes__chip" href="{{ $catalog->allUrl }}" @if (! $catalog->filtered) aria-current="true" @endif>{{ trans('webx-recipes::site.all') }}</a></li>
            @foreach ($catalog->nutrients as $nutrient)
                <li><a class="wx-recipes__chip" href="{{ $nutrient['url'] }}" rel="nofollow" @if ($nutrient['active']) aria-current="true" @endif>{{ $nutrient['title'] }}</a></li>
            @endforeach
        </ul>
    @endif

    @if ($catalog->items === [])
        <p>{{ trans('webx-recipes::site.empty') }}</p>
    @else
        <ul class="wx-recipes__grid">
            @foreach ($catalog->items as $card)
                @webxPart('recipe-card', ['card' => $card], 'webx-recipes::partials.card')
            @endforeach
        </ul>
    @endif

    @if (count($catalog->pages) > 1)
        <nav aria-label="{{ trans('webx-recipes::site.pages') }}">
            <ul class="wx-recipes__pages">
                @if ($catalog->previous)
                    <li><a href="{{ $catalog->previous }}" rel="prev">{{ trans('webx-recipes::site.previous') }}</a></li>
                @endif
                @foreach ($catalog->pages as $link)
                    <li><a href="{{ $link['url'] }}" @if ($link['current']) aria-current="page" @endif>{{ $link['page'] }}</a></li>
                @endforeach
                @if ($catalog->next)
                    <li><a href="{{ $catalog->next }}" rel="next">{{ trans('webx-recipes::site.next') }}</a></li>
                @endif
            </ul>
        </nav>
    @endif

    @if ($catalog->more && $catalog->items !== [])
        <p><a class="wx-recipes__more" href="{{ $catalog->more }}">{{ trans('webx-recipes::site.all-recipes') }}</a></p>
    @endif
</div>
