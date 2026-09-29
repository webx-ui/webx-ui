{{--
    A category of the storefront (§10.1): its trail, its heading, its text on the plain page, the
    filter, the sorts, the grid and the pages. `$page` is a CatalogPage — the products, the filter
    as links, the sorts — worked out by the handler; this view only draws it.

    Overridden by the site in `resources/views/vendor/webx-catalog/category.blade.php`, or a part
    at a time: `filter`, `grid`, `card`, `sort`, `pagination`, `breadcrumbs`.
--}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $page))

<x-dynamic-component :component="config('webx-catalog.layout') ?: 'webx-catalog::standalone'">
    <x-slot:head>
        @webxSeo($page)
        @if ($meta->title === null)
            <title>{{ $page->heading }}</title>
        @endif
    </x-slot:head>

    <article class="webx-catalog-category">
        <header>
            @include('webx-catalog::breadcrumbs', ['for' => $page])
            <h1>{{ $meta->h1 ?? $page->heading }}</h1>
            @if ($page->isPlain() && $category->description)
                <div class="webx-catalog-category__description">{!! $category->description !!}</div>
            @endif
        </header>

        <div class="webx-catalog-listing">
            @include('webx-catalog::filter', ['page' => $page])

            <section>
                @include('webx-catalog::sort', ['page' => $page])
                @include('webx-catalog::grid', ['page' => $page])
                @include('webx-catalog::pagination', ['products' => $page->products])
            </section>
        </div>
    </article>
</x-dynamic-component>
