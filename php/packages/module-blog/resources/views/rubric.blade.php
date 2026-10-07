@php($lead = $rubric->leadHtml())

<x-dynamic-component :component="config('webx-blog.layout') ?: 'webx-blog::standalone'">
    <x-slot:head>
        {{-- The SEO card, then the rubric's own title, lead and cover where the card is empty. --}}
        @webxSeo($rubric)
        <link rel="alternate" type="application/rss+xml" title="{{ trans('webx-blog::blog.rss') }}" href="{{ route('webx.blog.rss') }}">
    </x-slot:head>

    {{-- From the same list as the BreadcrumbList in the <head>. --}}
    @if (config('webx-blog.breadcrumbs', true))
        <x-webx-seo::breadcrumbs :for="$rubric" />
    @endif

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
