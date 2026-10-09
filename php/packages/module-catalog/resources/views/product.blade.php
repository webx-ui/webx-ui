{{--
    The page of a product that is on sale (§5, §10.1). The least markup that works, meant to be
    overridden by the site in `resources/views/vendor/webx-catalog/product.blade.php`: a product
    page is the part of a shop that looks like the shop.

    `$verdict` is Purchasability's answer: "Buy" when it says yes, "Ask the price" for a price on
    request, and the reason otherwise. There is no cart yet, so the buttons are the site's to wire.

    A picture with a video attached is the poster of the widgets' <x-webx-video> (§6 of the video
    spec), in the picture's own shape: a video of YouTube waits for the visitor's consent to media
    and puts the player in on a click, so nothing of YouTube loads and no cookie is set before; a
    file of the site is a <video preload="none">. Without JS a video of YouTube is a link to it.

    Two points for the satellites: `catalog.product.aside` beside the price (the stock, the brand)
    and `catalog.product.tabs` under the description (properties, related products).
--}}
@php($priced = (bool) config('webx-catalog.price.enabled', true))
@php($images = $product->images)
@php($videos = (bool) config('webx-catalog.fields.video', true))

<x-dynamic-component :component="config('webx-catalog.layout') ?: 'webx-catalog::standalone'">
    <x-slot:head>
        {{-- The SEO card, then the product's own name, summary and main picture where the card is
             empty (`seoFallback()`), then the site defaults. --}}
        @webxSeo($product)
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
                    @php($video = $videos ? $image->videoData() : null)
                    @if ($video !== null)
                        @php($poster = ['url' => $image->url(), 'width' => $image->width, 'height' => $image->height])
                        @php($shape = $image->width && $image->height ? $image->width.'/'.$image->height : null)
                        @php($name = $image->getTranslation('title') ?: ($image->getTranslation('alt') ?: $product->displayName()))
                        <figure class="webx-catalog-product__video">
                            @if ($video['embed'] === null)
                                <x-webx-video :file="$video['url']" :poster="$poster" :title="$name" :ratio="$shape" />
                            @else
                                <x-webx-video :src="$video['url']" :poster="$poster" :title="$name" :ratio="$shape" />
                            @endif
                        </figure>
                    @else
                        <img src="{{ $image->url() }}"
                             alt="{{ $image->getTranslation('alt') ?: $product->displayName() }}"
                             @if ($image->getTranslation('title')) title="{{ $image->getTranslation('title') }}" @endif
                             @if ($image->width) width="{{ $image->width }}" height="{{ $image->height }}" @endif
                             @if (! $loop->first) loading="lazy" @endif>
                    @endif
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
