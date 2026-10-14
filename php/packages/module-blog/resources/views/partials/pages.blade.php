{{--
    The pages of a listing: the widgets' pagination where the site has them — numbers, gaps,
    previous and next — and previous and next alone where it does not.

    Not `$articles->links()`: that renders the framework's own markup, which assumes a CSS
    framework this package does not ship and a site would only have to undo. `?page=` is where
    the number lives (§2.12). The tag goes through Blade::render in the PHP block: a tag nobody
    registered would not compile, and a template compiles whole.
--}}
@if ($articles->hasPages())
    @php
        $pagination = array_key_exists('webx-pagination', \Illuminate\Support\Facades\Blade::getClassComponentAliases())
            ? \Illuminate\Support\Facades\Blade::render('<x-webx-pagination class="webx-blog-pages" :paginator="$articles" />', ['articles' => $articles])
            : null;
    @endphp
    @if ($pagination !== null)
        {!! $pagination !!}
    @else
        <nav class="webx-blog-pager">
            @if ($articles->previousPageUrl())
                <a class="webx-blog-pager__link webx-blog-pager__link--prev" rel="prev" href="{{ $articles->previousPageUrl() }}">{{ trans('webx-blog::blog.previous') }}</a>
            @endif
            @if ($articles->nextPageUrl())
                <a class="webx-blog-pager__link webx-blog-pager__link--next" rel="next" href="{{ $articles->nextPageUrl() }}">{{ trans('webx-blog::blog.next') }}</a>
            @endif
        </nav>
    @endif
@endif
