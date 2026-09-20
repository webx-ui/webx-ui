{{--
    RSS 2.0.

    The declaration is spelled in pieces on purpose. Blade compiles a template by running
    `token_get_all()` over it and only touching the parts PHP calls inline HTML — so a literal
    `<?xml` is read as an open tag, everything up to the `?>` is read as PHP, and the file dies
    with "unexpected identifier version". Split, the two characters never sit next to each
    other and there is nothing to mistake.

    Nothing else in here escapes its output: everything printed is somebody's title or lead,
    and an ampersand in a title has to arrive as `&amp;` or the document stops being XML
    halfway down.
--}}
{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ trans('webx-blog::blog.title') }}</title>
        <link>{{ url('/') }}</link>
        <description>{{ trans('webx-blog::blog.rss-description') }}</description>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
        <atom:link href="{{ $url }}" rel="self" type="application/rss+xml"/>
        @foreach ($articles as $article)
            @php($rubric = $article->mainRubric())
            <item>
                <title>{{ $article->title }}</title>
                <link>{{ $article->url() }}</link>
                {{-- The address as the identifier: it is permanent, and a renamed article keeps
                     the old one as an alias, so a reader's client does not see it twice. --}}
                <guid isPermaLink="true">{{ $article->url() }}</guid>
                @if ($article->published_at)
                    <pubDate>{{ $article->published_at->toRfc2822String() }}</pubDate>
                @endif
                @if ($rubric)
                    <category>{{ $rubric->title }}</category>
                @endif
                @if ($article->lead)
                    <description>{{ $article->lead }}</description>
                @endif
            </item>
        @endforeach
    </channel>
</rss>
