{{--
    The cards of a listing, used by the feed, a rubric, a tag and "read next".

    `$articles` is a paginator or a plain collection; nothing here asks which, so the same
    partial serves both. Each card goes through `@webxPart`, so a theme's
    `parts/blog/card.blade.php` replaces the card without replacing the listing.
--}}
<ul class="webx-blog-cards">
    @foreach ($articles as $article)
        @webxPart('blog.card', ['article' => $article], 'webx-blog::partials.card')
    @endforeach
</ul>
