{{--
    A vacancy page: a fixed structure, one part per @include, so a site can publish one part —
    `vacancy/facts`, say — and leave the rest to the package. The order of the parts is this file;
    a site that wants another order publishes this one too. What every part is handed is listed
    in Rendering\VacancyPage.

    The parts first, the layout after: the head slot is worked out before the body.
--}}

@php(ob_start())
    <article class="wx-vacancy">
        @if (config('webx-vacancies.breadcrumbs', true))
            <x-webx-seo::breadcrumbs :for="$vacancy" />
        @endif

        @include('webx-vacancies::vacancy.heading')
        @include('webx-vacancies::vacancy.facts')
        @include('webx-vacancies::vacancy.description')
        @include('webx-vacancies::vacancy.lists')
        {{-- Nothing to apply to once the hiring is over. --}}
        @if (! $closed)
            @include('webx-vacancies::vacancy.apply')
        @endif
    </article>
@php($body = ob_get_clean())

<x-dynamic-component :component="config('webx-vacancies.layout') ?: 'webx-vacancies::standalone'">
    <x-slot:head>
        {{-- The SEO card, the vacancy's own name and lead under it (HasSeoFallback), the site
             defaults, the JobPosting of an open vacancy (HasStructuredData) and the noindex of a
             closed one. --}}
        @webxSeo($vacancy)
    </x-slot:head>

    {!! $body !!}
</x-dynamic-component>
