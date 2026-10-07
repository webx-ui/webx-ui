{{--
    An outlet's page: its name, a few words, and the articles it ran, one part per @include, so a
    site can publish one part — `outlet/articles`, say — and leave the rest to the package. What
    every part is handed is listed in Rendering\OutletPage.

    The parts first, the layout after: `@webxPartAssets` prints the bundle of what was rendered —
    a block a site puts into its own copy of this view brings its styles and script that way — and
    the head slot is worked out before the body.
--}}

@php(ob_start())
    <article class="wx-press-outlet">
        @if (config('webx-press.breadcrumbs', true))
            <x-webx-seo::breadcrumbs :for="$outlet" />
        @endif

        @include('webx-press::outlet.heading')
        @include('webx-press::outlet.facts')
        @include('webx-press::outlet.articles')
    </article>
@php($body = ob_get_clean())

<x-dynamic-component :component="config('webx-press.layout') ?: 'webx-press::standalone'">
    <x-slot:head>
        {{-- The SEO card, the outlet's own name, summary and logo under it (HasSeoFallback), the
             site defaults, and the ItemList of the articles (HasStructuredData). --}}
        @webxSeo($outlet)
        @webxPartAssets
    </x-slot:head>

    {!! $body !!}
</x-dynamic-component>
