{{--
    One card per article, used by the feed, a rubric, a tag and "read next".

    `$articles` is a paginator or a plain collection; nothing here asks which, so the same
    partial serves both.
--}}
<ul>
    @foreach ($articles as $article)
        <li>
            <a href="{{ $article->url() }}">
                @if ($url = $article->coverUrl())
                    <img src="{{ $url }}" alt="{{ $article->title }}" loading="lazy">
                @endif
                <h2>{{ $article->title }}</h2>
            </a>
            @if ($article->published_at)
                <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->locale(app()->getLocale())->isoFormat('LL') }}</time>
            @endif
            @if ($article->lead)
                <p>{{ $article->lead }}</p>
            @endif
        </li>
    @endforeach
</ul>
