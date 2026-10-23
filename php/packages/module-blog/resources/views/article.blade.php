{{-- The content first: `@webxBlocks` prints the bundle of what was rendered, so it has to run
     after the blocks themselves — and a slot is worked out before the layout around it, so this
     line has to stay at the top of the file rather than move inside the tag. --}}
@php($content = $article->renderBlocks())

<x-dynamic-component :component="config('webx-blog.layout') ?: 'webx-blog::standalone'" handle="blog.article">
    <x-slot:head>
        {{-- Everything the article says about itself: its SEO card, then its own title, lead and
             cover where the card is empty (`seoFallback()`), then the site defaults. --}}
        @webxSeo($article)
        {{-- The styles and scripts of exactly the block types this article used. --}}
        @webxBlocks
        <link rel="alternate" type="application/rss+xml" title="{{ trans('webx-blog::blog.rss') }}" href="{{ route('webx.blog.rss') }}">
    </x-slot:head>

    {{-- The page itself is the composition blog.article (COMPOSITIONS §10): a theme moves, removes,
         replaces and adds its nodes by id in compositions/blog.article/*.json instead of copying
         this file. --}}
    @include(composition_view('blog.article', 'blog.article'))
</x-dynamic-component>
