{{--
    The page of a product that is on sale (§5, §10.1). The least markup that works, meant to be
    overridden by the site in `resources/views/vendor/webx-catalog/product.blade.php`: a product
    page is the part of a shop that looks like the shop.
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
            <x-webx-seo::breadcrumbs :for="$product" />
            <h1>{{ $product->displayName() }}</h1>
            @if ($product->sku !== null)
                <p class="webx-catalog-product__sku">{{ __('webx-catalog::storefront.sku') }}: {{ $product->sku }}</p>
            @endif
        </header>

        @if ($images->isNotEmpty())
            <div class="webx-catalog-product__gallery">
                @foreach ($images as $image)
                    <img src="{{ $image->url() }}"
                         alt="{{ $image->getTranslation('alt') ?? $product->displayName() }}"
                         @if ($image->getTranslation('title')) title="{{ $image->getTranslation('title') }}" @endif
                         @if ($image->width) width="{{ $image->width }}" height="{{ $image->height }}" @endif
                         @if (! $loop->first) loading="lazy" @endif>
                @endforeach
            </div>
        @endif

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

        @if ($product->summary)
            <p class="webx-catalog-product__summary">{{ $product->summary }}</p>
        @endif

        @if ($product->description)
            <div class="webx-catalog-product__description">{!! $product->description !!}</div>
        @endif
    </article>
</x-dynamic-component>
