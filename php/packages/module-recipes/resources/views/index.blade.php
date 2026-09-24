@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), null))

<x-dynamic-component :component="config('webx-recipes.layout') ?: 'webx-recipes::standalone'">
    <x-slot:head>
        {{-- No entity: the index is a route, not a record, so what it says comes from the site's SEO defaults. --}}
        @webxSeo
        @if ($meta->title === null)
            <title>{{ trans('webx-recipes::site.title') }}</title>
        @endif
    </x-slot:head>

    <header>
        <h1>{{ trans('webx-recipes::site.title') }}</h1>
    </header>

    {{-- The categories are pages of their own; the nutrients are the filter inside the catalogue. --}}
    @if ($categories !== [])
        <nav aria-label="{{ trans('webx-recipes::site.categories') }}">
            <ul>
                @foreach ($categories as $category)
                    <li><a href="{{ $category->url() }}">{{ $category->title }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    @include('webx-recipes::partials.catalog', ['catalog' => $catalog])
</x-dynamic-component>
