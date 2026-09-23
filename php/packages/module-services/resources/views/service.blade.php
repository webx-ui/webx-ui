{{-- The content first: `@webxBlocks` prints the bundle of what was rendered, so it has to run
     after the blocks themselves — and a slot is worked out before the layout around it. --}}
@php($content = $service->renderBlocks())
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $service))

<x-dynamic-component :component="config('webx-services.layout') ?: 'webx-services::standalone'">
    <x-slot:head>
        {{-- Everything the service says about itself, from its SEO card and the site defaults. --}}
        @webxSeo($service)
        @if ($meta->title === null)
            <title>{{ $service->title }}</title>
        @endif
        {{-- The styles and scripts of exactly the block types this service used. --}}
        @webxBlocks
    </x-slot:head>

    <article>
        <header>
            {{-- Index, main category, the service: the same list as the BreadcrumbList in the <head>. --}}
            <x-webx-seo::breadcrumbs :for="$service" />
            <h1>{{ $service->title }}</h1>
        </header>

        {!! $content !!}
    </article>
</x-dynamic-component>
