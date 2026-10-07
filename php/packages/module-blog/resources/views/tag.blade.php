<x-dynamic-component :component="config('webx-blog.layout') ?: 'webx-blog::standalone'">
    <x-slot:head>
        {{--
            The tag is named on purpose. Whether this page carries `noindex` is worked out from it
            and from whether a rule covers this address (§12), and that answer comes out of
            `TagSource` — which recognises the tag only if it is handed one. The title is the tag's
            own word (`seoFallback()`) unless a rule names another.
        --}}
        @webxSeo($tag)
    </x-slot:head>

    {{-- From the same list as the BreadcrumbList in the <head>. --}}
    @if (config('webx-blog.breadcrumbs', true))
        <x-webx-seo::breadcrumbs :for="$tag" />
    @endif

    <header>
        <h1>{{ $tag->title }}</h1>
    </header>

    @if ($articles->isEmpty())
        <p>{{ trans('webx-blog::blog.empty') }}</p>
    @else
        @include('webx-blog::partials.cards')
        @include('webx-blog::partials.pages')
    @endif
</x-dynamic-component>
