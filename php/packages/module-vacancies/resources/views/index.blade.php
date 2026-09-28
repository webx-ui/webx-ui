{{-- The content first: a slot is worked out before the layout around it, the head before the
     body, and what the page pushes (its ItemList, its styles) has to be pushed by then. --}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), null))

@php(ob_start())
    @once
        @push('head')
            <style>
                .wx-vacancies__filter { display: flex; flex-wrap: wrap; gap: 0.5em 1em; margin: 0 0 1.5em; padding: 0; list-style: none; }
                .wx-vacancies__filter a[aria-current] { font-weight: 600; text-decoration: none; }
                .wx-vacancies__list { display: grid; gap: 1em; margin: 0 0 2em; padding: 0; list-style: none; }
                .wx-vacancies__card { display: flex; flex-direction: column; gap: 0.25em; }
                .wx-vacancies__link { font-weight: 600; }
                .wx-vacancies__meta { display: flex; flex-wrap: wrap; gap: 0.5em; opacity: 0.7; font-size: 0.9em; }
                .wx-vacancies__meta > * + *::before { content: "·"; margin-inline-end: 0.5em; }
                .wx-vacancies__lead { margin: 0; }
            </style>
        @endpush
    @endonce

    <header>
        <h1>{{ trans('webx-vacancies::site.title') }}</h1>
    </header>

    {{-- Links, not a form: without a script they still work, and a search engine reads them. --}}
    @if ($filter !== [])
        <nav aria-label="{{ trans('webx-vacancies::site.filter') }}">
            <ul class="wx-vacancies__filter">
                @foreach ($filter as $link)
                    <li><a href="{{ $link['url'] }}" @if ($link['current']) aria-current="page" @endif>{{ $link['title'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    <div class="wx-vacancies">
        @forelse ($groups as $group)
            @include('webx-vacancies::partials.group', ['group' => $group])
        @empty
            <p>{{ trans('webx-vacancies::site.empty') }}</p>
        @endforelse
    </div>
@php($body = ob_get_clean())

<x-dynamic-component :component="config('webx-vacancies.layout') ?: 'webx-vacancies::standalone'">
    <x-slot:head>
        {{-- No entity: the index is a route, not a record, so what it says comes from the SEO defaults. --}}
        @webxSeo
        @if ($meta->title === null)
            <title>{{ trans('webx-vacancies::site.title') }}</title>
        @endif
    </x-slot:head>

    {!! $body !!}
</x-dynamic-component>
