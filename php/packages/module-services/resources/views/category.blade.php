{{-- The blocks first: `@webxBlocks` prints the bundle of what was rendered, so it has to run after
     them — and a slot is worked out before the layout around it. --}}
@php($content = config('webx-services.categories.blocks') ? $category->renderBlocks() : '')
@php($lead = $category->leadHtml())

<x-dynamic-component :component="config('webx-services.layout') ?: 'webx-services::standalone'">
    <x-slot:head>
        {{-- The SEO card, the category's own name, lead and cover under it (HasSeoFallback). --}}
        @webxSeo($category)
        @webxBlocks
    </x-slot:head>

    {{-- From the same list as the BreadcrumbList in the <head>. --}}
    @if (config('webx-services.breadcrumbs', true))
        <x-webx-seo::breadcrumbs :for="$category" />
    @endif

    <header>
        <h1>{{ $category->title }}</h1>
        {{-- Printed raw: what is stored has already been through the allowlist of its field type. --}}
        @if ($lead !== '')
            <div class="services-lead">{!! $lead !!}</div>
        @endif
    </header>

    {!! $content !!}

    @if ($services->isEmpty())
        <p>{{ trans('webx-services::services.empty') }}</p>
    @else
        @include('webx-services::partials.cards')
    @endif
</x-dynamic-component>
