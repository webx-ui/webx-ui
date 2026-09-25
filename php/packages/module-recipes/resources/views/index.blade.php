{{-- The content first: `@webxPartAssets` prints the bundle of what was rendered, so it has to run
     after the catalogue — a customised card brings its own styles and script — and a slot is
     worked out before the layout around it, the head before the body. --}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), null))

@php(ob_start())
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
@php($body = ob_get_clean())

<x-dynamic-component :component="config('webx-recipes.layout') ?: 'webx-recipes::standalone'">
    <x-slot:head>
        {{-- No entity: the index is a route, not a record, so what it says comes from the site's SEO defaults. --}}
        @webxSeo
        @if ($meta->title === null)
            <title>{{ trans('webx-recipes::site.title') }}</title>
        @endif
        @webxPartAssets
    </x-slot:head>

    {!! $body !!}
</x-dynamic-component>
