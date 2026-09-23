@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $rubric))
@php($lead = $rubric->leadHtml())

<x-dynamic-component :component="config('webx-blog.layout') ?: 'webx-blog::standalone'">
    <x-slot:head>
        @webxSeo($rubric)
        @if ($meta->title === null)
            {{-- Nothing derives a title from the entity: the SEO card is written by hand and is
                 often empty, and a page with no <title> at all is worse than a plain one. --}}
            <title>{{ $rubric->title }}</title>
        @endif
        <link rel="alternate" type="application/rss+xml" title="{{ trans('webx-blog::blog.rss') }}" href="{{ route('webx.blog.rss') }}">
    </x-slot:head>

    {{-- From the same list as the BreadcrumbList in the <head>. --}}
    <x-webx-seo::breadcrumbs :for="$rubric" />

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
</x-dynamic-component>
