{{-- The date and the byline; the byline is a link only where the author has an open page (§19.1, decision 3). --}}@php($byline = $article->authorName())
@if ($article->published_at || $byline !== '')
<p class="webx-blog-article__meta">
    @if ($article->published_at)
    <time class="webx-blog-article__date" datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->locale(app()->getLocale())->isoFormat('LL') }}</time>
    @endif
    @if ($byline !== '')
    @if ($authorUrl = $article->authorUrl())
    <a class="webx-blog-article__author" href="{{ $authorUrl }}" rel="author">{{ $byline }}</a>
    @else
    <span class="webx-blog-article__author">{{ $byline }}</span>
    @endif
    @endif
</p>
@endif
