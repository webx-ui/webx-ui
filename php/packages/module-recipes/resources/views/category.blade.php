{{-- The content first, for the same reason as on the index: `@webxPartAssets` prints the
     bundle of what was rendered, and the head slot is worked out before the body. --}}
@php($lead = $category->leadHtml())
@php($picture = $category->picture())

@php(ob_start())
    @if (config('webx-recipes.breadcrumbs', true))
        <x-webx-seo::breadcrumbs :for="$category" />
    @endif

    <header class="wx-recipes__header">
        <h1 class="wx-recipes__title">{{ $category->title }}</h1>
        @if ($picture && $picture['url'])
            <img class="wx-recipes__picture" src="{{ $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}"
                 @if ($picture['width']) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif>
        @endif
        {{-- Printed raw: what is stored has already been through the allowlist of its field type. --}}
        @if ($lead !== '')
            <div class="wx-recipes-lead">{!! $lead !!}</div>
        @endif
    </header>

    @include('webx-recipes::partials.catalog', ['catalog' => $catalog])
@php($body = ob_get_clean())

<x-dynamic-component :component="config('webx-recipes.layout') ?: 'webx-recipes::standalone'" handle="recipes.category">
    <x-slot:head>
        {{-- The SEO card, the category's own name, lead and picture under it (HasSeoFallback). --}}
        @webxSeo($category)
        @webxPartAssets
    </x-slot:head>

    {!! $body !!}
</x-dynamic-component>
