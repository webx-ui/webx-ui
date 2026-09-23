@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), null))

<x-dynamic-component :component="config('webx-services.layout') ?: 'webx-services::standalone'">
    <x-slot:head>
        {{-- No entity: the index is a route, not a record, so what it says comes from the site's SEO defaults. --}}
        @webxSeo
        @if ($meta->title === null)
            <title>{{ trans('webx-services::services.title') }}</title>
        @endif
    </x-slot:head>

    <header>
        <h1>{{ trans('webx-services::services.title') }}</h1>
    </header>

    @if ($categories->every(fn ($category) => $category->services->isEmpty()) && $uncategorised->isEmpty())
        <p>{{ trans('webx-services::services.empty') }}</p>
    @endif

    {{-- Each category in its order, each one's services in the order they were dragged into inside it. --}}
    @foreach ($categories as $category)
        @if ($category->services->isNotEmpty())
            <section>
                <h2><a href="{{ $category->url() }}">{{ $category->title }}</a></h2>
                @include('webx-services::partials.cards', ['services' => $category->services])
            </section>
        @endif
    @endforeach

    @if ($uncategorised->isNotEmpty())
        <section>
            @if ($categories->isNotEmpty())
                <h2>{{ trans('webx-services::services.other') }}</h2>
            @endif
            @include('webx-services::partials.cards', ['services' => $uncategorised])
        </section>
    @endif
</x-dynamic-component>
