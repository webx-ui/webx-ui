@if ($article->tags->isNotEmpty())
<footer class="webx-blog-article__footer">
    <ul class="webx-blog-tags">
        @foreach ($article->tags as $tag)
        <li class="webx-blog-tags__item"><a class="webx-blog-tags__link" href="{{ $tag->url() }}">{{ $tag->title }}</a></li>
        @endforeach
    </ul>
</footer>
@endif
