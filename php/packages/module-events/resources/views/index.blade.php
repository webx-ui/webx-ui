{{-- The content first: a slot is worked out before the layout around it, the head before the
     body, and what the list pushes (its styles) has to be pushed by then. --}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), null))

@php(ob_start())
    <header>
        <h1>{{ trans('webx-events::site.title') }}</h1>
    </header>

    {{-- The categories are pages of their own, each listing its own events to come. --}}
    @if ($categories !== [])
        <nav aria-label="{{ trans('webx-events::site.categories') }}">
            <ul>
                @foreach ($categories as $category)
                    <li><a href="{{ $category->url() }}">{{ $category->title }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    @include('webx-events::partials.list', ['listing' => $listing])
@php($body = ob_get_clean())

<x-dynamic-component :component="config('webx-events.layout') ?: 'webx-events::standalone'">
    <x-slot:head>
        {{-- No entity: the index is a route, not a record, so what it says comes from the SEO defaults. --}}
        @webxSeo
        @if ($meta->title === null)
            <title>{{ trans('webx-events::site.title') }}</title>
        @endif
    </x-slot:head>

    {!! $body !!}
</x-dynamic-component>
