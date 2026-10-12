{{--
    The catalogue — the index, a category page and the "Recipes" block all print this, so a site
    that publishes it changes all three at once. `$catalog` is a Rendering\Catalog: cards, the
    nutrient filter and the pages as ready links, or a showcase with a link to all recipes.

    Links, not buttons: without a script the filter and the pages still work, and a search engine
    reads the pages the way a reader clicks through them.
--}}
@include('webx-recipes::partials.look')

<div class="wx-recipes">
    @if ($catalog->nutrients !== [])
        <ul class="wx-recipes__filter" aria-label="{{ trans('webx-recipes::site.filter') }}">
            <li class="wx-recipes__filter-item"><a class="wx-recipes__chip" href="{{ $catalog->allUrl }}" @if (! $catalog->filtered) aria-current="true" @endif>{{ trans('webx-recipes::site.all') }}</a></li>
            @foreach ($catalog->nutrients as $nutrient)
                <li class="wx-recipes__filter-item"><a class="wx-recipes__chip" href="{{ $nutrient['url'] }}" rel="nofollow" @if ($nutrient['active']) aria-current="true" @endif>{{ $nutrient['title'] }}</a></li>
            @endforeach
        </ul>
    @endif

    @if ($catalog->items === [])
        <p class="wx-recipes__empty">{{ trans('webx-recipes::site.empty') }}</p>
    @else
        {{-- Three columns at most unless the block asked for another number; a narrow page gets fewer. --}}
        <ul @class(['wx-recipes__grid', 'wx-recipes__grid--'.$catalog->columns => $catalog->columns !== WebxUi\Recipes\Rendering\Catalog::COLUMNS])>
            @foreach ($catalog->items as $card)
                @webxPart('recipe-card', ['card' => $card], 'webx-recipes::partials.card')
            @endforeach
        </ul>
    @endif

    @if (count($catalog->pages) > 1)
        <nav class="wx-recipes__pager" aria-label="{{ trans('webx-recipes::site.pages') }}">
            <ul class="wx-recipes__pages">
                @if ($catalog->previous)
                    <li class="wx-recipes__page"><a class="wx-recipes__page-link" href="{{ $catalog->previous }}" rel="prev">{{ trans('webx-recipes::site.previous') }}</a></li>
                @endif
                @foreach ($catalog->pages as $link)
                    <li class="wx-recipes__page"><a class="wx-recipes__page-link" href="{{ $link['url'] }}" @if ($link['current']) aria-current="page" @endif>{{ $link['page'] }}</a></li>
                @endforeach
                @if ($catalog->next)
                    <li class="wx-recipes__page"><a class="wx-recipes__page-link" href="{{ $catalog->next }}" rel="next">{{ trans('webx-recipes::site.next') }}</a></li>
                @endif
            </ul>
        </nav>
    @endif

    @if ($catalog->more && $catalog->items !== [])
        <p class="wx-recipes__all"><a class="wx-recipes__more" href="{{ $catalog->more }}">{{ trans('webx-recipes::site.all-recipes') }}</a></p>
    @endif
</div>
