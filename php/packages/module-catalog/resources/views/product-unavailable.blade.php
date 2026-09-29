{{--
    The trimmed page of a product that is not on sale (§5): unpublished, or in no visible category.
    The name, the main picture, the article number, the badge — and the point where
    `module-catalog-links` puts what to take instead. The response carries `noindex` whatever this
    view prints.
--}}
@php($image = $product->mainImage())
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $product))

<x-dynamic-component :component="config('webx-catalog.layout') ?: 'webx-catalog::standalone'">
    <x-slot:head>
        @webxSeo($product)
        @if ($meta->title === null)
            <title>{{ $product->displayName() }}</title>
        @endif
    </x-slot:head>

    <article class="webx-catalog-product webx-catalog-product--unavailable">
        <header>
            <h1>{{ $product->displayName() }}</h1>
            <p class="webx-catalog-product__badge">{{ __('webx-catalog::storefront.unavailable') }}</p>
            @if ($product->sku !== null)
                <p class="webx-catalog-product__sku">{{ __('webx-catalog::storefront.sku') }}: {{ $product->sku }}</p>
            @endif
        </header>

        @if ($image !== null)
            <img src="{{ $image->url() }}" alt="{{ $image->getTranslation('alt') ?? $product->displayName() }}">
        @endif

        @webxPart('catalog.product.unavailable', ['product' => $product], 'webx-catalog::partials.unavailable')
    </article>
</x-dynamic-component>
