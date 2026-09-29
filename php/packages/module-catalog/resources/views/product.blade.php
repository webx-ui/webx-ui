{{--
    The page of a product that is on sale (§5, §10.1). The least markup that works, meant to be
    overridden by the site in `resources/views/vendor/webx-catalog/product.blade.php`: a product
    page is the part of a shop that looks like the shop.

    `$verdict` is Purchasability's answer: "Buy" when it says yes, "Ask the price" for a price on
    request, and the reason otherwise. There is no cart yet, so the buttons are the site's to wire.

    Two points for the satellites: `catalog.product.aside` beside the price (the stock, the brand)
    and `catalog.product.tabs` under the description (properties, related products).
--}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $product))
@php($priced = (bool) config('webx-catalog.price.enabled', true))
@php($images = $product->images)

<x-dynamic-component :component="config('webx-catalog.layout') ?: 'webx-catalog::standalone'">
    <x-slot:head>
        @webxSeo($product)
        @if ($meta->title === null)
            <title>{{ $product->displayName() }}</title>
        @endif
    </x-slot:head>

    <article class="webx-catalog-product">
        <header>
            @include('webx-catalog::breadcrumbs', ['for' => $product])
            <h1>{{ $product->displayName() }}</h1>
            @if ($product->sku !== null)
                <p class="webx-catalog-product__sku">{{ __('webx-catalog::storefront.sku') }}: {{ $product->sku }}</p>
            @endif
        </header>

        @if ($images->isNotEmpty())
            <div class="webx-catalog-product__gallery">
                @foreach ($images as $image)
                    <img src="{{ $image->url() }}"
                         alt="{{ $image->getTranslation('alt') ?: $product->displayName() }}"
                         @if ($image->getTranslation('title')) title="{{ $image->getTranslation('title') }}" @endif
                         @if ($image->width) width="{{ $image->width }}" height="{{ $image->height }}" @endif
                         @if (! $loop->first) loading="lazy" @endif>
                @endforeach
            </div>
        @endif

        <div class="webx-catalog-product__buy">
            @if ($priced)
                <p class="webx-catalog-product__price">
                    @if ($product->price !== null)
                        @if ($product->old_price !== null)
                            <s>{{ $product->old_price }}</s>
                        @endif
                        <strong>{{ $product->price }}</strong>
                        @if ($product->unit !== null)
                            / {{ __('webx-catalog::units.'.$product->unit) }}
                        @endif
                    @else
                        {{ __('webx-catalog::storefront.price-on-request') }}
                    @endif
                </p>
            @endif

            @if ($verdict->purchasable)
                <button type="button" class="webx-catalog-product__action" data-product="{{ $product->id }}">{{ __('webx-catalog::storefront.buy') }}</button>
            @elseif ($verdict->code === WebxUi\Catalog\Purchase\CoreRules::PRICE_ON_REQUEST)
                <button type="button" class="webx-catalog-product__action" data-product="{{ $product->id }}">{{ __('webx-catalog::storefront.ask-price') }}</button>
            @else
                <p class="webx-catalog-product__refusal">{{ $verdict->label }}</p>
            @endif

            @webxPart('catalog.product.aside', ['product' => $product, 'verdict' => $verdict], 'webx-catalog::points.product-aside')
        </div>

        @if ($product->summary)
            <p class="webx-catalog-product__summary">{{ $product->summary }}</p>
        @endif

        @if ($product->description)
            <div class="webx-catalog-product__description">{!! $product->description !!}</div>
        @endif

        @webxPart('catalog.product.tabs', ['product' => $product], 'webx-catalog::points.product-tabs')
    </article>
</x-dynamic-component>
