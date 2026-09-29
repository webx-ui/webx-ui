{{--
    One product in a grid: the main picture, the name, the price, and two points for the
    satellites — `catalog.card.badges` over the picture (labels: "new", "sale") and
    `catalog.card.meta` under the price (the stock). `$verdict` says whether it can be bought.
--}}
@php($image = $product->mainImage())
@php($priced = (bool) config('webx-catalog.price.enabled', true))
<article class="webx-catalog-card">
    <a href="{{ $product->listedUrl() }}">
        @webxPart('catalog.card.badges', ['product' => $product], 'webx-catalog::points.card-badges')
        @if ($image !== null)
            <img src="{{ $image->url() }}" alt="{{ $image->getTranslation('alt') ?: $product->displayName() }}" loading="lazy"
                 @if ($image->width) width="{{ $image->width }}" height="{{ $image->height }}" @endif>
        @endif
        <h2 class="webx-catalog-card__name">{{ $product->displayName() }}</h2>
    </a>
    @if ($priced)
        <p class="webx-catalog-card__price">
            @if ($product->price !== null)
                @if ($product->old_price !== null)
                    <s>{{ $product->old_price }}</s>
                @endif
                <strong>{{ $product->price }}</strong>
            @else
                {{ __('webx-catalog::storefront.price-on-request') }}
            @endif
        </p>
    @endif
    @webxPart('catalog.card.meta', ['product' => $product, 'verdict' => $verdict], 'webx-catalog::points.card-meta')
</article>
