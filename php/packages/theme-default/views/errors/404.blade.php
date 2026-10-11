@php
    /**
     * A page that does not exist, in the site's own frame (THEMES §4): the header with its
     * language switcher — which leads to each language's home page here, marked `is-fallback`
     * (WIDGETS §13) — the footer, the theme's styles, the request's language.
     *
     * The head is handed over whole, so `@webxSeo` stays out of it: there is no entity to describe,
     * and a canonical or `hreflang` pointing at a missing address would only be wrong. The regions
     * still need the styles of their blocks, hence `@webxBlocks`.
     */
    $home = class_exists(\WebxUi\Routing\SiteUrl::class) && app()->bound(\WebxUi\Localization\Locales::class)
        ? url(app(\WebxUi\Routing\SiteUrl::class)->prefix() ?: '/')
        : url('/');
@endphp

<x-layout>
    <x-slot:head>
        <title>{{ __('theme::theme.not_found.title') }} — {{ config('app.name') }}</title>
        <meta name="robots" content="noindex">
        @webxBlocks
    </x-slot:head>

    <section class="site-error">
        <p class="site-error__code">404</p>
        <h1>{{ __('theme::theme.not_found.title') }}</h1>
        <p>{{ __('theme::theme.not_found.text') }}</p>
        <a class="site-error__home" href="{{ $home }}">{{ __('theme::theme.not_found.home') }}</a>
    </section>
</x-layout>
