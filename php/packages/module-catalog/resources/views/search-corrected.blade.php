{{--
    Above a list found for other words than the ones typed (decision 16 of the Manticore spec):
    what was searched for instead, and a way back to the words as typed. A site words it in its
    own dictionary (`search-corrected`, `search-instead`) or draws it its own way by overriding this
    view. `$page` is the search page; it is drawn only when the engine corrected something.
--}}
@php($typed = (string) $page->search)
@php($corrected = (string) $page->result->corrected)
@php($base = route('webx.catalog.search'))

<p class="webx-catalog-search__corrected">
    {!! __('webx-catalog::storefront.search-corrected', ['corrected' => '<a href="'.e($base.'?'.http_build_query(['q' => $corrected])).'"><strong>'.e($corrected).'</strong></a>']) !!}
    <span class="webx-catalog-search__instead">
        {!! __('webx-catalog::storefront.search-instead', ['q' => '<a href="'.e($base.'?'.http_build_query(['q' => $typed, 'typed' => '1'])).'">'.e($typed).'</a>']) !!}
    </span>
</p>
