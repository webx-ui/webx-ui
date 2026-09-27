{{--
    One article: its title leads out — to the address, else to the PDF (decision 8) — in a new
    window, since the reader came here to see what was written about the site and will want to
    come back. A PDF that is not where the title leads is a second link.
--}}
<li class="wx-press-article">
    <h2 class="wx-press-article__title">
        @if ($article['target'])
            <a href="{{ $article['target'] }}" target="_blank" rel="noopener">{{ $article['title'] }}</a>
        @else
            {{ $article['title'] }}
        @endif
        @if ($article['pdf'] && ! $article['url'])
            <span class="wx-press-article__pdf">{{ trans('webx-press::site.pdf') }}</span>
        @endif
    </h2>
    @if ($article['kind_label'] || $article['when'] !== '')
        <p class="wx-press-article__meta">
            @if ($article['kind_label'])
                <span class="wx-press-article__kind">{{ $article['kind_label'] }}</span>
            @endif
            @if ($article['when'] !== '')
                <time datetime="{{ $article['date'] }}">{{ $article['when'] }}</time>
            @endif
        </p>
    @endif
    @if ($article['excerpt'] !== '')
        <p class="wx-press-article__excerpt">{!! nl2br(e($article['excerpt'])) !!}</p>
    @endif
    @if ($article['url'] && $article['pdf'])
        <p class="wx-press-article__links">
            <a href="{{ $article['url'] }}" target="_blank" rel="noopener">{{ trans('webx-press::site.read') }}</a>
            · <a href="{{ $article['pdf'] }}" target="_blank" rel="noopener">{{ trans('webx-press::site.pdf') }}</a>
        </p>
    @endif
</li>
