<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- No entity: the feed is a route, not a record (§2.11), so what it says comes from the site's SEO defaults. --}}
    @webxSeo
    <link rel="alternate" type="application/rss+xml" title="{{ trans('webx-blog::blog.rss') }}" href="{{ route('webx.blog.rss') }}">
</head>
<body>
<header>
    <h1>{{ trans('webx-blog::blog.title') }}</h1>
    @if ($rubrics->isNotEmpty())
        <nav>
            <ul>
                @foreach ($rubrics as $rubric)
                    <li><a href="{{ $rubric->url() }}">{{ $rubric->title }}</a></li>
                @endforeach
            </ul>
        </nav>
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
