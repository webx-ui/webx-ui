@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $tag))
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('webx-blog::partials.base')
    {{--
        The tag is named on purpose. Whether this page carries `noindex` is worked out from it
        and from whether a rule covers this address (§12), and that answer comes out of
        `TagSource` — which recognises the tag only if it is handed one.
    --}}
    @webxSeo($tag)
    @if ($meta->title === null)
        {{-- Nothing derives a title from the entity: the SEO card is written by hand and is
             often empty, and a page with no <title> at all is worse than a plain one. --}}
        <title>{{ $tag->title }}</title>
    @endif
</head>
<body>
<header>
    <h1>{{ $tag->title }}</h1>
</header>

@if ($articles->isEmpty())
    <p>{{ trans('webx-blog::blog.empty') }}</p>
@else
    @include('webx-blog::partials.cards')
    @include('webx-blog::partials.pages')
@endif
</body>
</html>
