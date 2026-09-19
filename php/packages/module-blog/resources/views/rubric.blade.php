<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @webxSeo($rubric)
    <link rel="alternate" type="application/rss+xml" title="{{ trans('webx-blog::blog.rss') }}" href="{{ route('webx.blog.rss') }}">
</head>
<body>
<header>
    <h1>{{ $rubric->title }}</h1>
    @if ($rubric->lead)
        <p>{{ $rubric->lead }}</p>
    @endif
</header>

@if ($articles->isEmpty())
    <p>{{ trans('webx-blog::blog.empty') }}</p>
@else
    @include('webx-blog::partials.cards')
    @include('webx-blog::partials.pages')
@endif
</body>
</html>
