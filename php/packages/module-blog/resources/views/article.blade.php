{{-- The content first: `@webxBlocks` prints the bundle of what was rendered, so it has to run
     after the blocks themselves — and a slot is worked out before the layout around it, so this
     line has to stay at the top of the file rather than move inside the tag. --}}
@php($content = $article->renderBlocks())

<x-dynamic-component :component="config('webx-blog.layout') ?: 'webx-blog::standalone'">
    <x-slot:head>
        {{-- Everything the article says about itself: its SEO card, then its own title, lead and
             cover where the card is empty (`seoFallback()`), then the site defaults. --}}
        @webxSeo($article)
        {{-- The styles and scripts of exactly the block types this article used. --}}
        @webxBlocks
        <link rel="alternate" type="application/rss+xml" title="{{ trans('webx-blog::blog.rss') }}" href="{{ route('webx.blog.rss') }}">
    </x-slot:head>

    <article class="webx-blog-article">
        <header class="webx-blog-article__header">
            {{-- Feed, main rubric, the article: the same list as the BreadcrumbList in the <head>. --}}
            @if (config('webx-blog.breadcrumbs', true))
                <x-webx-seo::breadcrumbs :for="$article" />
            @endif
            <h1 class="webx-blog-article__title">{{ $article->title }}</h1>
            @php($byline = $article->authorName())
            @if ($article->published_at || $byline !== '')
                <p class="webx-blog-article__meta">
                    @if ($article->published_at)
                        <time class="webx-blog-article__date" datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->locale(app()->getLocale())->isoFormat('LL') }}</time>
                    @endif
                    {{-- A link only where the author has an open page (§19.1, decision 3). --}}
                    @if ($byline !== '')
                        @if ($authorUrl = $article->authorUrl())
                            <a class="webx-blog-article__author" href="{{ $authorUrl }}" rel="author">{{ $byline }}</a>
                        @else
                            <span class="webx-blog-article__author">{{ $byline }}</span>
                        @endif
                    @endif
                </p>
            @endif
            @if ($url = $article->coverUrl())
                <img class="webx-blog-article__cover" src="{{ $url }}" alt="{{ $article->title }}">
            @endif
        </header>

        {!! $content !!}

        @if ($article->tags->isNotEmpty())
            <footer class="webx-blog-article__footer">
                <ul class="webx-blog-tags">
                    @foreach ($article->tags as $tag)
                        <li class="webx-blog-tags__item"><a class="webx-blog-tags__link" href="{{ $tag->url() }}">{{ $tag->title }}</a></li>
                    @endforeach
                </ul>
            </footer>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <aside class="webx-blog-related">
            <h2 class="webx-blog-related__title">{{ trans('webx-blog::blog.related') }}</h2>
            @include('webx-blog::partials.cards', ['articles' => $related])
        </aside>
    @endif
</x-dynamic-component>
