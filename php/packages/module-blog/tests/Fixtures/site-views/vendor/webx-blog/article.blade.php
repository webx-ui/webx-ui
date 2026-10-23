{{-- A site's copy of the whole article view, from before compositions: it still wins. --}}
<x-dynamic-component :component="config('webx-blog.layout') ?: 'webx-blog::standalone'" handle="blog.article">
    <article class="site-copy"><h1>{{ $article->title }}</h1></article>
</x-dynamic-component>
