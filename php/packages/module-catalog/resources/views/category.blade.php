{{--
    A category of the storefront (§10.1), and a landing, which is a category's page with a set
    chosen in advance: the trail, the heading, the owner's texts on the plain page — one above the
    products, one under the pages — the filter, the sorts, the grid and the pages, and the points
    `catalog.listing.top` and `catalog.listing.bottom` the satellites write into. `$page` is a
    CatalogPage — the products, the filter as links, the sorts — worked out by the handler; this
    view only draws it.

    Overridden by the site in `resources/views/vendor/webx-catalog/category.blade.php`, or a part
    at a time: `filter`, `grid`, `card`, `sort`, `pagination`, `breadcrumbs`.
--}}
{{-- Asked here only for the <h1>: an SEO card may name a heading other than the category's name. --}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $page))
@php($owner = $page->owner())
@php($texts = $page->isPlain() && $owner instanceof WebxUi\Catalog\Storefront\HasListingTexts ? $owner : null)
@php($above = $texts?->textAbove($page->context->locale))
@php($below = $texts?->textBelow($page->context->locale))

<x-dynamic-component :component="config('webx-catalog.layout') ?: 'webx-catalog::standalone'">
    <x-slot:head>
        {{-- The title is in there too: the card's, or the page's heading through the site's
             template, with the category's description and cover under it (`seoFallback()`). --}}
        @webxSeo($page)
    </x-slot:head>

    <article class="webx-catalog-category">
        <header>
            @include('webx-catalog::breadcrumbs', ['for' => $page])
            <h1>{{ $meta->h1 ?? $page->heading }}</h1>
            @if ($above)
                <div class="webx-catalog-category__description">{!! $above !!}</div>
            @endif
        </header>

        <div class="webx-catalog-listing">
            @include('webx-catalog::filter', ['page' => $page])

            <section>
                @webxPart('catalog.listing.top', ['page' => $page], 'webx-catalog::points.listing-top')
                @include('webx-catalog::sort', ['page' => $page])
                @include('webx-catalog::grid', ['page' => $page])
                @include('webx-catalog::pagination', ['products' => $page->products])
                @if ($below)
                    <div class="webx-catalog-category__text">{!! $below !!}</div>
                @endif
                @webxPart('catalog.listing.bottom', ['page' => $page], 'webx-catalog::points.listing-bottom')
            </section>
        </div>
    </article>
</x-dynamic-component>
