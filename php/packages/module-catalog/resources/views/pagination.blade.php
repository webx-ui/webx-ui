{{--
    The pages of the list: previous, the numbers around the current one, next. `?page=` keeps the
    sort; a page past the first is closed to the index (§10.3), but its links are followed — that
    is how the products on it are found.

    Not `$products->links()`: that draws the framework's markup for a CSS framework this package
    does not ship.
--}}
@if ($products->hasPages())
    @php($current = $products->currentPage())
    @php($last = $products->lastPage())
    <nav class="webx-catalog-pagination" aria-label="{{ __('webx-catalog::storefront.pages') }}">
        @if ($products->previousPageUrl())
            <a rel="prev" href="{{ $products->previousPageUrl() }}">{{ __('webx-catalog::storefront.previous') }}</a>
        @endif
        @foreach (range(max(1, $current - 2), min($last, $current + 2)) as $number)
            @if ($number === $current)
                <span class="is-current" aria-current="page">{{ $number }}</span>
            @else
                <a href="{{ $products->url($number) }}">{{ $number }}</a>
            @endif
        @endforeach
        @if ($products->nextPageUrl())
            <a rel="next" href="{{ $products->nextPageUrl() }}">{{ __('webx-catalog::storefront.next') }}</a>
        @endif
    </nav>
@endif
