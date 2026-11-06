{{--
    The stock status of one product, on a card or beside the buy button. The tone is a class, never a
    colour; a site that wants its own line publishes this view (`webx-catalog-stock-views`).

    A card says a status that cannot be bought once: its name stands in place of the buy button
    (`webx-catalog::buy`), so the line stays silent there. The product page keeps both.
--}}
@php($productStock = isset($product) ? ($stock[$product->id] ?? null) : null)
@if ($productStock !== null && ($productStock['purchasable'] || $stockPoint === 'catalog.product.aside'))
    <p class="wx-catalog-stock wx-catalog-stock--{{ $productStock['color'] }} {{ $stockPoint === 'catalog.product.aside' ? 'wx-catalog-stock--product' : 'wx-catalog-stock--card' }}" data-stock="{{ $productStock['code'] }}">{{ $productStock['name'] }}</p>
@endif
