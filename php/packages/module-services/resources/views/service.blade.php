{{-- The content first: `@webxBlocks` prints the bundle of what was rendered, so it has to run
     after the blocks themselves — and a slot is worked out before the layout around it. --}}
@php($content = $service->renderBlocks())

<x-dynamic-component :component="config('webx-services.layout') ?: 'webx-services::standalone'">
    <x-slot:head>
        {{-- Everything the service says about itself: its SEO card, its own name, lead and cover
             under it (HasSeoFallback), and the site defaults. --}}
        @webxSeo($service)
        {{-- The styles and scripts of exactly the block types this service used. --}}
        @webxBlocks
    </x-slot:head>

    <article>
        <header>
            {{-- Index, main category, the service: the same list as the BreadcrumbList in the <head>. --}}
            @if (config('webx-services.breadcrumbs', true))
                <x-webx-seo::breadcrumbs :for="$service" />
            @endif
            <h1>{{ $service->title }}</h1>
        </header>

        {!! $content !!}
    </article>
</x-dynamic-component>
