{{-- The content first: a slot is worked out before the layout around it, the head before the
     body, and what the page pushes (its ItemList, its styles) has to be pushed by then. --}}

@php(ob_start())
    {{-- Under a theme the base look is resources/theme/vacancies.css in the modules' stylesheet,
         which the theme restyles by coming later; this inline copy would come later still and win. --}}
    @unless (function_exists('theme_active') && theme_active())
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
    @endunless

    <header class="wx-vacancies__header">
        <h1 class="wx-vacancies__title">{{ trans('webx-vacancies::site.title') }}</h1>
    </header>

    {{-- Links, not a form: without a script they still work, and a search engine reads them. --}}
    @if ($filter !== [])
        <nav class="wx-vacancies__nav" aria-label="{{ trans('webx-vacancies::site.filter') }}">
            <ul class="wx-vacancies__filter">
                @foreach ($filter as $link)
                    <li class="wx-vacancies__filter-item"><a class="wx-vacancies__filter-link" href="{{ $link['url'] }}" @if ($link['current']) aria-current="page" @endif>{{ $link['title'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    <div class="wx-vacancies">
        @forelse ($groups as $group)
            @include('webx-vacancies::partials.group', ['group' => $group])
        @empty
            <p class="wx-vacancies__empty">{{ trans('webx-vacancies::site.empty') }}</p>
        @endforelse
    </div>
@php($body = ob_get_clean())

<x-dynamic-component :component="config('webx-vacancies.layout') ?: 'webx-vacancies::standalone'" handle="vacancies.index">
    <x-slot:head>
        {{-- No entity: the index is a route, not a record. A rule for its address or the site's
             defaults say what they have; the title under them is the section's name, and it goes
             through the title template like any other. --}}
        @webxSeo(fallback: ['title' => trans('webx-vacancies::site.title')])
    </x-slot:head>

    {!! $body !!}
</x-dynamic-component>
