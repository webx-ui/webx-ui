<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{--
        The tag is named on purpose. Whether this page carries `noindex` is worked out from it
        and from whether a rule covers this address (§12), and that answer comes out of
        `TagSource` — which recognises the tag only if it is handed one.
    --}}
    @webxSeo($tag)
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
