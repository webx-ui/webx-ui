
<x-dynamic-component :component="config('webx-services.layout') ?: 'webx-services::standalone'">
    <x-slot:head>
        {{-- No entity: the index is a route, not a record. A rule for its address or the site's
             defaults say what they have; the title under them is the section's name, and it goes
             through the title template like any other. --}}
        @webxSeo(fallback: ['title' => trans('webx-services::services.title')])
    </x-slot:head>

    <header class="webx-services-header">
        <h1 class="webx-services-header__title">{{ trans('webx-services::services.title') }}</h1>
    </header>

    @if ($categories->every(fn ($category) => $category->services->isEmpty()) && $uncategorised->isEmpty())
        <p class="webx-services-empty">{{ trans('webx-services::services.empty') }}</p>
    @endif

    {{-- Each category in its order, each one's services in the order they were dragged into inside it. --}}
    @foreach ($categories as $category)
        @if ($category->services->isNotEmpty())
            <section class="webx-services-group">
                <h2 class="webx-services-group__title"><a class="webx-services-group__link" href="{{ $category->url() }}">{{ $category->title }}</a></h2>
                @include('webx-services::partials.cards', ['services' => $category->services])
            </section>
        @endif
    @endforeach

    @if ($uncategorised->isNotEmpty())
        <section class="webx-services-group">
            @if ($categories->isNotEmpty())
                <h2 class="webx-services-group__title">{{ trans('webx-services::services.other') }}</h2>
            @endif
            @include('webx-services::partials.cards', ['services' => $uncategorised])
        </section>
    @endif
</x-dynamic-component>
