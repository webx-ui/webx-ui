{{-- The content first: `@webxBlocks` prints the bundle of what was rendered, so it has to run after the blocks themselves. --}}
@php($content = $article->renderBlocks())
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Everything the article says about itself; the title is in there too. --}}
    @webxSeo($article)
    {{-- The styles and scripts of exactly the block types this article used. --}}
    @webxBlocks
    <link rel="alternate" type="application/rss+xml" title="{{ trans('webx-blog::blog.rss') }}" href="{{ route('webx.blog.rss') }}">
</head>
<body>
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
</body>
</html>
