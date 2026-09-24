@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $category))
@php($lead = $category->leadHtml())
@php($picture = $category->picture())

<x-dynamic-component :component="config('webx-recipes.layout') ?: 'webx-recipes::standalone'">
    <x-slot:head>
        @webxSeo($category)
        @if ($meta->title === null)
            <title>{{ $category->title }}</title>
        @endif
    </x-slot:head>

    @if (config('webx-recipes.breadcrumbs', true))
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
            <div class="wx-recipes-lead">{!! $lead !!}</div>
        @endif
    </header>

    @include('webx-recipes::partials.catalog', ['catalog' => $catalog])
</x-dynamic-component>
