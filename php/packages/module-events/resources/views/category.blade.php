{{-- The content first, for the same reason as on the index: the head slot is worked out before
     the body, and the list pushes into it. --}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $category))
@php($lead = $category->leadHtml())
@php($picture = $category->picture())

@php(ob_start())
    @if (config('webx-events.breadcrumbs', true))
        <x-webx-seo::breadcrumbs :for="$category" />
    @endif

    <header>
        <h1>{{ $category->title }}</h1>
        @if ($picture && $picture['url'])
            <img src="{{ $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}"
                 @if ($picture['width']) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif>
        @endif
        {{-- Printed raw: what is stored has already been through the allowlist of its field type. --}}
        @if ($lead !== '')
            <div class="wx-events-lead">{!! $lead !!}</div>
        @endif
    </header>

    @include('webx-events::partials.list', ['listing' => $listing])
@php($body = ob_get_clean())

<x-dynamic-component :component="config('webx-events.layout') ?: 'webx-events::standalone'">
    <x-slot:head>
        @webxSeo($category)
        @if ($meta->title === null)
            <title>{{ $category->title }}</title>
        @endif
    </x-slot:head>

    {!! $body !!}
</x-dynamic-component>
