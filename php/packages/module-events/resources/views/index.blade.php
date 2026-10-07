{{-- The content first: a slot is worked out before the layout around it, the head before the
     body, and what the list pushes (its styles) has to be pushed by then. --}}

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
        {{-- No entity: the index is a route, not a record. A rule for its address or the site's
             defaults say what they have; the title under them is the section's name, and it goes
             through the title template like any other. --}}
        @webxSeo(fallback: ['title' => trans('webx-events::site.title')])
    </x-slot:head>

    {!! $body !!}
</x-dynamic-component>
