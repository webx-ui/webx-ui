{{--
    The orders a reader is offered (`webx-catalog.sorts`), as links. `?sort=` is the only query of
    the catalogue, and a sorted page is closed to the index with its canonical on the unsorted one
    (§7.2) — the links say `nofollow` so nobody is sent to count them.
--}}
@if (count($page->sorts) > 1 && ! $page->products->isEmpty())
    <nav class="webx-catalog-sort" aria-label="{{ __('webx-catalog::storefront.sort') }}">
        @foreach ($page->sorts as $sort)
            @if ($sort['selected'])
                <span class="is-selected" aria-current="true">{{ $sort['label'] }}</span>
            @else
                <a href="{{ $sort['url'] }}" rel="nofollow">{{ $sort['label'] }}</a>
            @endif
        @endforeach
    </nav>
@endif
