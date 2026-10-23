@if ($url = $article->coverUrl())
<img class="webx-blog-article__cover" src="{{ $url }}" alt="{{ $article->title }}">
@endif
