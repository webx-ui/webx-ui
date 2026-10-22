{{--
    One article in a listing: `$article` is a `WebxUi\Blog\Models\Article`.

    The feed, a rubric, a tag and "read next" print it through the point `blog.card` (THEMES
    §10.4): a theme that wants another card writes `views/parts/blog/card.blade.php` — keeping the
    `<li>`, because the list around it is still this module's — and every listing follows.
--}}
<li class="webx-blog-card">
    <a class="webx-blog-card__link" href="{{ $article->url() }}">
        @if ($url = $article->coverUrl())
            <img class="webx-blog-card__cover" src="{{ $url }}" alt="{{ $article->title }}" loading="lazy">
        @endif
        <h2 class="webx-blog-card__title">{{ $article->title }}</h2>
    </a>
    @if ($article->published_at)
        <time class="webx-blog-card__date" datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->locale(app()->getLocale())->isoFormat('LL') }}</time>
    @endif
    @if ($article->lead)
        {{-- Through PageText: the lead takes shortcodes, and a bare `[phone]` is not for a visitor. --}}
        <p class="webx-blog-card__lead">{{ app(\WebxUi\Admin\Shortcodes\PageText::class)->text($article->lead) }}</p>
    @endif
</li>
