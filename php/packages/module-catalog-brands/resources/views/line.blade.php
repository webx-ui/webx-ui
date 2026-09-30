{{--
    The brand of one product, on a card or beside the buy button, linking to its page. A hidden
    brand is not in `$brands` at all. A site that wants its own line publishes this view
    (`webx-catalog-brands-views`).
--}}
@php($productBrand = isset($product) ? ($brands[$product->id] ?? null) : null)
@if ($productBrand !== null)
    <p class="wx-catalog-brand {{ $brandPoint === 'catalog.product.aside' ? 'wx-catalog-brand--product' : 'wx-catalog-brand--card' }}" data-brand="{{ $productBrand['id'] }}">
        @if ($productBrand['url'] !== null)
            <a href="{{ $productBrand['url'] }}">{{ $productBrand['name'] }}</a>
        @else
            {{ $productBrand['name'] }}
        @endif
    </p>
@endif
