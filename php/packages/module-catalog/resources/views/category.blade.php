{{--
    The least a category page can be: its name and its text, so that a deleted product's redirect
    lands somewhere. The listing, the filter and the rest of the storefront come with the engine.
--}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $category))

<x-dynamic-component :component="config('webx-catalog.layout') ?: 'webx-catalog::standalone'">
    <x-slot:head>
        @webxSeo($category)
        @if ($meta->title === null)
            <title>{{ $category->displayName() }}</title>
        @endif
    </x-slot:head>

    <article class="webx-catalog-category">
        <header>
            <x-webx-seo::breadcrumbs :for="$category" />
            <h1>{{ $category->displayName() }}</h1>
        </header>

        {!! $category->description !!}
    </article>
</x-dynamic-component>
