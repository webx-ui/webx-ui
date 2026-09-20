@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $rubric))
@php($lead = $rubric->leadHtml())
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('webx-blog::partials.base')
    @webxSeo($rubric)
    @if ($meta->title === null)
        {{-- Nothing derives a title from the entity: the SEO card is written by hand and is
             often empty, and a page with no <title> at all is worse than a plain one. --}}
        <title>{{ $rubric->title }}</title>
    @endif
    <link rel="alternate" type="application/rss+xml" title="{{ trans('webx-blog::blog.rss') }}" href="{{ route('webx.blog.rss') }}">
</head>
<body>
<header>
    <h1>{{ $rubric->title }}</h1>
    {{-- A document and not a line since the panel grew an editor for it: printed raw, because
         what is stored has already been through the allowlist of its field type. --}}
    @if ($lead !== '')
        <div class="rubric-lead">{!! $lead !!}</div>
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
