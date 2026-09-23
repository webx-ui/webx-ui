{{-- The content first: `@webxBlocks` prints the bundle of what was rendered, so it has to run
     after the blocks themselves — and a slot is worked out before the layout around it, so this
     line has to stay at the top of the file rather than move inside the tag. --}}
@php($content = $article->renderBlocks())
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $article))

<x-dynamic-component :component="config('webx-blog.layout') ?: 'webx-blog::standalone'">
    <x-slot:head>
        {{-- Everything the article says about itself, from its SEO card and the site defaults. --}}
        @webxSeo($article)
        @if ($meta->title === null)
            {{-- Nothing derives a title from the entity: the SEO card is written by hand and is
                 often empty, and a page with no <title> at all is worse than a plain one. --}}
            <title>{{ $article->title }}</title>
        @endif
        {{-- The styles and scripts of exactly the block types this article used. --}}
        @webxBlocks
        <link rel="alternate" type="application/rss+xml" title="{{ trans('webx-blog::blog.rss') }}" href="{{ route('webx.blog.rss') }}">
    </x-slot:head>

    <article>
        <header>
            @if ($rubric)
                <nav><a href="{{ $rubric->url() }}">{{ $rubric->title }}</a></nav>
            @endif
            <h1>{{ $article->title }}</h1>
            @if ($article->published_at)
                <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->locale(app()->getLocale())->isoFormat('LL') }}</time>
            @endif
            @if ($article->author)
                <p>{{ $article->author->name }}</p>
            @endif
            @if ($url = $article->coverUrl())
                <img src="{{ $url }}" alt="{{ $article->title }}">
            @endif
        </header>

        {!! $content !!}

        @if ($article->tags->isNotEmpty())
            <footer>
                <ul>
                    @foreach ($article->tags as $tag)
                        <li><a href="{{ $tag->url() }}">{{ $tag->title }}</a></li>
                    @endforeach
                </ul>
            </footer>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <aside>
            <h2>{{ trans('webx-blog::blog.related') }}</h2>
            @include('webx-blog::partials.cards', ['articles' => $related])
        </aside>
    @endif
</x-dynamic-component>
