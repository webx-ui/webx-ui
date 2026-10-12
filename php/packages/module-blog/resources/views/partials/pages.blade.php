{{--
    Previous and next, and nothing else.

    Not `$articles->links()`: that renders the framework's own markup, which assumes a CSS
    framework this package does not ship and a site would only have to undo. Two links are what
    a listing actually needs, and `?page=` is where the number lives (§2.12).
--}}
@if ($articles->hasPages())
    <nav class="webx-blog-pager">
        @if ($articles->previousPageUrl())
            <a class="webx-blog-pager__link webx-blog-pager__link--prev" rel="prev" href="{{ $articles->previousPageUrl() }}">{{ trans('webx-blog::blog.previous') }}</a>
        @endif
        @if ($articles->nextPageUrl())
            <a class="webx-blog-pager__link webx-blog-pager__link--next" rel="next" href="{{ $articles->nextPageUrl() }}">{{ trans('webx-blog::blog.next') }}</a>
        @endif
    </nav>
@endif
