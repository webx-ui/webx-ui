{{--
    Previous and next, and nothing else.

    Not `$articles->links()`: that renders the framework's own markup, which assumes a CSS
    framework this package does not ship and a site would only have to undo. Two links are what
    a listing actually needs, and `?page=` is where the number lives (§2.12).
--}}
@if ($articles->hasPages())
    <nav>
        @if ($articles->previousPageUrl())
            <a rel="prev" href="{{ $articles->previousPageUrl() }}">{{ trans('webx-blog::blog.previous') }}</a>
        @endif
        @if ($articles->nextPageUrl())
            <a rel="next" href="{{ $articles->nextPageUrl() }}">{{ trans('webx-blog::blog.next') }}</a>
        @endif
    </nav>
@endif
