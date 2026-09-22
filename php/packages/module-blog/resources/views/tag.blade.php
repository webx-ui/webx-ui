@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $tag))

<x-dynamic-component :component="config('webx-blog.layout') ?: 'webx-blog::standalone'">
    <x-slot:head>
        {{--
            The tag is named on purpose. Whether this page carries `noindex` is worked out from it
            and from whether a rule covers this address (§12), and that answer comes out of
            `TagSource` — which recognises the tag only if it is handed one.
        --}}
        @webxSeo($tag)
        @if ($meta->title === null)
            {{-- Nothing derives a title from the entity: the SEO card is written by hand and is
                 often empty, and a page with no <title> at all is worse than a plain one. --}}
            <title>{{ $tag->title }}</title>
        @endif
    </x-slot:head>

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
