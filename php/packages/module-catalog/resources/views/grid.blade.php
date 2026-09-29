{{--
    The products of the page in the engine's order, a card each — or the words for an empty list.
--}}
@if ($page->products->isEmpty())
    <p class="webx-catalog-grid__empty">{{ __('webx-catalog::storefront.empty') }}</p>
@else
    <p class="webx-catalog-grid__total">{{ __('webx-catalog::storefront.total', ['count' => $page->products->total()]) }}</p>
    <ul class="webx-catalog-grid">
        @foreach ($page->products as $product)
            <li>@include('webx-catalog::card', ['product' => $product, 'verdict' => $page->verdict($product)])</li>
        @endforeach
    </ul>
@endif
