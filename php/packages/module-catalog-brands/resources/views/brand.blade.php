{{--
    A brand's page (§2.3 of the dictionaries spec): the catalogue's list narrowed to the brand, with
    its logo and its description on top of the plain page. `$page` is the core's CatalogPage, so the
    filter, the grid, the sorts and the pages are the catalogue's own partials; `$brand` is the
    brand.

    Overridden by the site in `resources/views/vendor/webx-catalog-brands/brand.blade.php`.
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

    <article class="webx-catalog-brand">
        <header>
            @include('webx-catalog::breadcrumbs', ['for' => $page])
            @if ($logo = $brand->logoUrl())
                <img class="webx-catalog-brand__logo" src="{{ $logo }}" alt="{{ $brand->displayName(app()->getLocale()) }}">
            @endif
            <h1>{{ $meta->h1 ?? $page->heading }}</h1>
            @if ($page->isPlain() && ($description = $brand->descriptionHtml()) !== '')
                <div class="webx-catalog-brand__description">{!! $description !!}</div>
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
